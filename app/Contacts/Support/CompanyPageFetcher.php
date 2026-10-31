<?php

namespace App\Contacts\Support;

use App\Support\PublicNetworkGuard;
use App\Support\RegistrableDomain;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Fetches a company's /about, /team and /company pages as plain text, politely and without any
 * route to private networks: https only, every hop resolved through PublicNetworkGuard and pinned
 * to a vetted address (CURLOPT_RESOLVE keeps the hostname for TLS), no proxy, redirects followed by
 * hand only within the same registrable domain, robots.txt respected and one request per second
 * per domain. Never crawls links. Any failure skips the path; nothing is thrown.
 *
 * Body cap: content decoding is off (Accept-Encoding: identity, any other Content-Encoding is
 * skipped), so wire bytes equal body bytes. on_headers rejects oversized Content-Length, encoded
 * and (for pages) non-HTML responses before the body is read; a curl transfer-info callback aborts
 * mid-stream past the cap for chunked or length-less bodies; the received size is checked again
 * before the body is read. Http::fake only exercises the last check (it skips curl and on_headers).
 */
class CompanyPageFetcher
{
    /** @var list<string> */
    private const array PATHS = ['/about', '/team', '/company'];

    private const string USER_AGENT = 'talent-labs/0.1 (+company page check)';

    private const int MAX_REDIRECTS = 2;

    private const int MAX_BODY_BYTES = 512 * 1024;

    private const int MAX_TEXT_CHARS = 8000;

    private const int MIN_INTERVAL_MICROSECONDS = 1_000_000;

    /** @var array<string, float> registrable domain => microtime the last request finished */
    private array $lastRequestAt = [];

    public function __construct(
        private readonly PublicNetworkGuard $guard,
        private readonly OutreachSuppressionList $suppressionList,
    ) {}

    /**
     * @return array<string, string> path => plain text, only for pages that were fetched
     */
    public function fetch(string $domain): array
    {
        $pages = [];

        try {
            $host = $this->bareHost($domain);
            $registrable = $host === null ? null : RegistrableDomain::of($host);

            if ($host === null || $registrable === null || $this->suppressionList->coversDomain($registrable)) {
                return [];
            }

            $robots = $this->robots($host, $registrable);

            foreach (self::PATHS as $path) {
                if (! $robots->isAllowed($path)) {
                    continue;
                }

                $response = $this->get("https://{$host}{$path}", $registrable, htmlOnly: true);

                if ($response === null || $response->status() !== 200 || ! $this->isHtml($response)) {
                    continue;
                }

                $text = $this->toText($response->body());

                if ($text !== '') {
                    $pages[$path] = $text;
                }
            }
        } catch (Throwable) {
            return $pages;
        }

        return $pages;
    }

    /**
     * The normalized host when the input is a bare hostname (no port, path, query, fragment or
     * userinfo), otherwise null.
     */
    private function bareHost(string $value): ?string
    {
        $host = strtolower(trim($value));
        $host = str_ends_with($host, '.') ? substr($host, 0, -1) : $host;

        if ($host === '' || preg_match('#[/?\#@:\[\]\\\\\s]#', $host) === 1 || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        $parts = parse_url("https://{$host}/");

        if (! is_array($parts) || array_keys($parts) !== ['scheme', 'host', 'path'] || ($parts['host'] ?? null) !== $host || ($parts['path'] ?? null) !== '/') {
            return null;
        }

        return $host;
    }

    private function robots(string $host, string $registrable): RobotsRules
    {
        $response = $this->get("https://{$host}/robots.txt", $registrable, htmlOnly: false);

        return match (true) {
            $response === null => RobotsRules::disallowAll(),
            $response->status() === 200 => RobotsRules::parse($response->body()),
            $response->status() === 404 => RobotsRules::allowAll(),
            default => RobotsRules::disallowAll(),
        };
    }

    /**
     * The final non-redirect response, following at most MAX_REDIRECTS same-domain hops by hand.
     */
    private function get(string $url, string $registrable, bool $htmlOnly): ?Response
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->request($url, $registrable, $htmlOnly);

            if ($response === null || ! $response->redirect()) {
                return $response;
            }

            $url = $this->redirectTarget($url, $response->header('Location'), $registrable);

            if ($url === null) {
                return null;
            }
        }

        return null;
    }

    private function request(string $url, string $registrable, bool $htmlOnly): ?Response
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass']) || ($parts['port'] ?? 443) !== 443) {
            return null;
        }

        $host = $this->bareHost((string) ($parts['host'] ?? ''));

        if ($host === null) {
            return null;
        }

        $addresses = $this->guard->publicAddresses($host);

        if ($addresses === []) {
            return null;
        }

        $address = $addresses[0];
        $pinned = str_contains($address, ':') ? "[{$address}]" : $address;
        $cap = self::MAX_BODY_BYTES;

        $this->throttle($registrable);

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->connectTimeout(3)
                ->timeout(5)
                ->withoutRedirecting()
                ->withOptions([
                    'proxy' => '',
                    'decode_content' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$host}:443:{$pinned}"],
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                        CURLOPT_PROXY => '',
                        CURLOPT_MAXFILESIZE => $cap,
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_XFERINFOFUNCTION => static fn ($handle, int $downloadTotal, int $downloaded): int => $downloaded > $cap ? 1 : 0,
                    ],
                    'on_headers' => function (ResponseInterface $response) use ($htmlOnly): void {
                        if (! $this->acceptableHeaders(
                            $response->getStatusCode(),
                            $response->getHeaderLine('Content-Length'),
                            $response->getHeaderLine('Content-Encoding'),
                            $response->getHeaderLine('Content-Type'),
                            $htmlOnly,
                        )) {
                            throw new RuntimeException('Company page response rejected before download.');
                        }
                    },
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        } finally {
            $this->lastRequestAt[$registrable] = microtime(true);
        }

        if (! $this->acceptableHeaders($response->status(), $response->header('Content-Length'), $response->header('Content-Encoding'), $response->header('Content-Type'), $htmlOnly)) {
            return null;
        }

        $size = $response->toPsrResponse()->getBody()->getSize();

        if ($size === null || $size > self::MAX_BODY_BYTES) {
            return null;
        }

        return $response;
    }

    /**
     * Redirects and non-200 responses only need the size and encoding checks; a 200 page must be HTML.
     */
    private function acceptableHeaders(int $status, string $contentLength, string $contentEncoding, string $contentType, bool $htmlOnly): bool
    {
        $encoding = strtolower(trim($contentEncoding));

        if ((int) $contentLength > self::MAX_BODY_BYTES || ($encoding !== '' && $encoding !== 'identity')) {
            return false;
        }

        return ! $htmlOnly || $status !== 200 || str_starts_with(strtolower(trim($contentType)), 'text/html');
    }

    private function redirectTarget(string $from, string $location, string $registrable): ?string
    {
        $location = trim($location);

        if ($location === '') {
            return null;
        }

        if (str_starts_with($location, '//')) {
            $location = 'https:'.$location;
        } elseif (str_starts_with($location, '/')) {
            $host = parse_url($from, PHP_URL_HOST);
            $location = is_string($host) ? "https://{$host}{$location}" : '';
        }

        $parts = parse_url($location);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = $this->bareHost((string) ($parts['host'] ?? ''));

        if ($host === null || RegistrableDomain::of($host) !== $registrable) {
            return null;
        }

        return $location;
    }

    private function throttle(string $registrable): void
    {
        $last = $this->lastRequestAt[$registrable] ?? null;

        if ($last === null) {
            return;
        }

        $waitMicroseconds = (int) ceil(self::MIN_INTERVAL_MICROSECONDS - (microtime(true) - $last) * 1_000_000);

        if ($waitMicroseconds > 0) {
            usleep($waitMicroseconds);
        }
    }

    private function isHtml(Response $response): bool
    {
        return str_starts_with(strtolower(trim($response->header('Content-Type'))), 'text/html');
    }

    private function toText(string $html): string
    {
        $html = mb_scrub($html, 'UTF-8');
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $html);
        $html = (string) preg_replace('#<!--.*?-->#s', ' ', $html);
        $text = (string) preg_replace('#<[^>]*>#', ' ', $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));

        return mb_substr($text, 0, self::MAX_TEXT_CHARS);
    }
}
