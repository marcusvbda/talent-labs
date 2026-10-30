<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Resolves a host and accepts it only when every address is public (no loopback, private,
 * link-local, metadata, reserved or documentation ranges), to block SSRF.
 *
 * Callers connect to a returned address, not the hostname, with the TLS peer name set to the
 * original host, so DNS rebinding cannot swap the address between check and connect.
 * Mandatory for the client SMTP transport (`email-providers` feature) and company website
 * fetches (`contact-quality` feature). InteractsWithJobBoardApi is exempt.
 */
class PublicNetworkGuard
{
    private const array BLOCKED_IPV4 = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    private const array BLOCKED_IPV6 = [
        '::/128',
        '::1/128',
        '::/96',
        '64:ff9b::/96',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    /**
     * Every resolved address, IPv4 first; empty when unresolved or when ANY address is not public.
     *
     * @return list<string>
     */
    public function publicAddresses(string $host): array
    {
        $host = rtrim(trim($host), '.');

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if ($host === '') {
            return [];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicAddress($host) ? [$host] : [];
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            return [];
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return [];
            }
        }

        return $addresses;
    }

    public function isPublicAddress(string $ip): bool
    {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ! IpUtils::checkIp($ip, self::BLOCKED_IPV4);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        $mapped = $this->embeddedMappedIpv4($ip);

        if ($mapped !== null) {
            return $this->isPublicAddress($mapped);
        }

        return ! IpUtils::checkIp($ip, self::BLOCKED_IPV6);
    }

    /**
     * The IPv4 inside an IPv4-mapped IPv6 address (::ffff:a.b.c.d or ::ffff:a00:1), otherwise null.
     */
    private function embeddedMappedIpv4(string $ip): ?string
    {
        $packed = inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        if (substr($packed, 0, 12) !== str_repeat("\0", 10)."\xff\xff") {
            return null;
        }

        $ipv4 = inet_ntop(substr($packed, 12, 4));

        return $ipv4 === false ? null : $ipv4;
    }

    /**
     * A/AAAA records plus hosts-file entries (gethostbynamel), deduplicated, IPv4 first.
     * Empty when the resolver failed.
     *
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $hostsFile = gethostbynamel($host);
        } catch (Throwable) {
            return [];
        } finally {
            restore_error_handler();
        }

        if (! is_array($records)) {
            return [];
        }

        $ipv4 = is_array($hostsFile) ? $hostsFile : [];
        $ipv6 = [];

        foreach ($records as $record) {
            if (is_string($record['ip'] ?? null)) {
                $ipv4[] = $record['ip'];
            }

            if (is_string($record['ipv6'] ?? null)) {
                $ipv6[] = $record['ipv6'];
            }
        }

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }
}
