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
        $hosts = [];

        foreach ($this->mxRecords($domain) as $record) {
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
        $records = $this->mxRecords($domain);

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
     * Raw MX records sorted by priority; null MX entries keep an empty host.
     *
     * @return list<array{host: string, pri: int}>
     */
    private function mxRecords(string $domain): array
    {
        $records = [];

        foreach ($this->query($domain, DNS_MX) as $record) {
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
        if (trim($domain) === '') {
            return [];
        }

        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($domain, $type);
        } catch (Throwable) {
            return [];
        } finally {
            restore_error_handler();
        }

        return is_array($records) ? $records : [];
    }
}
