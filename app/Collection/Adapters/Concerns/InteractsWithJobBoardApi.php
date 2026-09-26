<?php

namespace App\Collection\Adapters\Concerns;

use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

trait InteractsWithJobBoardApi
{
    /**
     * Shared HTTP client for all job board adapters (throws on non-2xx).
     */
    protected function http(): PendingRequest
    {
        return Http::timeout(20)
            ->retry(2, 500)
            ->acceptJson()
            ->withUserAgent('talent-labs/0.1 (local)')
            ->throw();
    }

    /**
     * Upper bound of HTTP requests a single source may make in one run.
     */
    protected const MAX_REQUESTS_PER_RUN = 10;

    /**
     * GET one URL; null when the server rate-limits us (HTTP 429), so callers stop
     * requesting and keep what they already collected (see stopOnRateLimit()). Every
     * call is a single attempt (no retries) so the per-run request budget holds; other
     * failures are rethrown. `$headers` replace same-named defaults (e.g. `Accept` for XML feeds).
     *
     * @param  array<string, string|int>  $query
     * @param  array<string, string>  $headers
     */
    protected function getOrNullWhenRateLimited(string $url, array $query = [], array $headers = []): ?Response
    {
        try {
            return $this->http()->retry(1)->replaceHeaders($headers)->get($url, $query);
        } catch (RequestException $e) {
            if ($this->isRateLimited($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function isRateLimited(Throwable $e): bool
    {
        return $e instanceof RequestException && $e->response->status() === 429;
    }

    /**
     * Called when a request was rate-limited: keeping partial data is fine, but a 429
     * before anything was collected must fail the source run instead of "succeeding" empty.
     *
     * @param  array<array-key, mixed>  $collected
     */
    protected function stopOnRateLimit(array $collected): void
    {
        if ($collected === []) {
            throw new RuntimeException('Rate limited by the source (HTTP 429) before any posting was collected.');
        }
    }

    /**
     * Short pause between sequential requests to the same board (not before the first).
     */
    protected function pause(): void
    {
        usleep(250_000);
    }

    /**
     * A list setting: comma-separated string or array of strings; trimmed, no empties,
     * unique, order preserved.
     *
     * @return list<string>
     */
    protected function settingList(Source $source, string $key): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];
        $value = $settings[$key] ?? null;

        $parts = match (true) {
            is_string($value) => explode(',', $value),
            is_array($value) => $value,
            default => [],
        };

        $list = [];

        foreach ($parts as $part) {
            $part = $this->stringOrNull($part);

            if ($part !== null && ! in_array($part, $list, true)) {
                $list[] = $part;
            }
        }

        return $list;
    }

    /**
     * The board identifier (company slug) these adapters cannot work without.
     */
    protected function requireIdentifier(Source $source): string
    {
        $identifier = $this->stringOrNull($source->identifier);

        if ($identifier === null) {
            throw new InvalidArgumentException("Source #{$source->id} ({$source->name}) has no identifier.");
        }

        return $identifier;
    }

    /**
     * Keep only array items that are string-keyed payload objects.
     *
     * @return list<array<string, mixed>>
     */
    protected function items(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    protected function boolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A date sent as Unix seconds (int or numeric string).
     */
    protected function timestampOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_int($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $value);
    }

    /**
     * Plain text from HTML: tags stripped, entities decoded, whitespace collapsed.
     */
    protected function htmlToText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }
}
