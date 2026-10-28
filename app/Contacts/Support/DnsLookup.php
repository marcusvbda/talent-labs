<?php

namespace App\Contacts\Support;

use Throwable;

class DnsLookup
{
    /**
     * Mail exchanger hostnames ordered by priority (primary first), excluding null MX entries.
     *
     * @return list<string>
     */
    public function mxHosts(string $domain): array
    {
        return $this->mxHostsOrNull($domain) ?? [];
    }

    /**
     * Like mxHosts(), but null when the resolver failed (timeout, SERVFAIL) instead of answering.
     * NXDOMAIN and "no MX" are answers and give an empty list.
     *
     * @return list<string>|null
     */
    public function mxHostsOrNull(string $domain): ?array
    {
        $records = $this->mxRecords($domain);

        if ($records === null) {
            return null;
        }

        $hosts = [];

        foreach ($records as $record) {
            if ($record['host'] !== '') {
                $hosts[] = $record['host'];
            }
        }

        return $hosts;
    }

    /**
     * Whether the domain publishes MX records and all of them are null MX (RFC 7505).
     */
    public function hasOnlyNullMx(string $domain): bool
    {
        $records = $this->mxRecords($domain) ?? [];

        if ($records === []) {
            return false;
        }

        foreach ($records as $record) {
            if ($record['host'] !== '') {
                return false;
            }
        }

        return true;
    }

    public function hasAddress(string $domain): bool
    {
        return $this->query($domain, DNS_A) !== [] || $this->query($domain, DNS_AAAA) !== [];
    }

    /**
     * Raw MX records sorted by priority; null MX entries keep an empty host. Null when the lookup failed.
     *
     * @return list<array{host: string, pri: int}>|null
     */
    private function mxRecords(string $domain): ?array
    {
        $answer = $this->lookup($domain, DNS_MX);

        if ($answer === null) {
            return null;
        }

        $records = [];

        foreach ($answer as $record) {
            $target = is_string($record['target'] ?? null) ? $record['target'] : '';

            $records[] = [
                'host' => rtrim(mb_strtolower(trim($target)), '.'),
                'pri' => is_numeric($record['pri'] ?? null) ? (int) $record['pri'] : PHP_INT_MAX,
            ];
        }

        usort($records, fn (array $a, array $b): int => $a['pri'] <=> $b['pri']);

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function query(string $domain, int $type): array
    {
        return $this->lookup($domain, $type) ?? [];
    }

    /**
     * dns_get_record() answers NXDOMAIN / no data with an empty array and only returns false
     * (with a warning) when the resolver itself failed; that failure is reported as null.
     *
     * @return list<array<string, mixed>>|null
     */
    private function lookup(string $domain, int $type): ?array
    {
        if (trim($domain) === '') {
            return [];
        }

        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($domain, $type);
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        return is_array($records) ? $records : null;
    }
}
