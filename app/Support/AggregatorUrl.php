<?php

namespace App\Support;

final class AggregatorUrl
{
    /**
     * True when the URL can't be the company's own page or ATS: null, empty, not http(s), or hosted
     * on a talent.outreach.aggregator_domains entry (the host equals it or is a subdomain of it;
     * a `name.*` entry matches any registrable domain starting with `name.`).
     */
    public static function is(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return true;
        }

        $host = strtolower(rtrim($host, '.'));
        $domain = RegistrableDomain::of($url);

        /** @var list<string> $entries */
        $entries = config('talent.outreach.aggregator_domains', []);

        foreach ($entries as $entry) {
            $entry = strtolower(trim($entry));

            if ($entry === '') {
                continue;
            }

            if (str_ends_with($entry, '.*')) {
                $prefix = substr($entry, 0, -1);

                if ($domain !== null && str_starts_with($domain, $prefix)) {
                    return true;
                }

                continue;
            }

            if ($host === $entry || str_ends_with($host, ".{$entry}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * The URL's registrable domain ("https://jobs.lever.co/x" -> "lever.co"), or null.
     */
    public static function domain(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url === '' ? null : RegistrableDomain::of($url);
    }
}
