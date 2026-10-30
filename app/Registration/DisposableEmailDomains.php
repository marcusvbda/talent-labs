<?php

namespace App\Registration;

/**
 * Throwaway-inbox domains refused by open registration. The list lives in
 * resources/data (disposable-email-domains project, CC0) and a subdomain of a
 * listed domain counts as listed too.
 */
final class DisposableEmailDomains
{
    /** @var array<string, true>|null */
    private ?array $domains = null;

    public function isBlocked(string $email): bool
    {
        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $labels = explode('.', mb_strtolower(trim(mb_substr($email, $at + 1))));
        $domains = $this->domains();

        // `a.mailinator.com` checks itself, then `mailinator.com`; a bare TLD is never tested.
        for ($i = 0; $i < count($labels) - 1; $i++) {
            if (isset($domains[implode('.', array_slice($labels, $i))])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function domains(): array
    {
        if ($this->domains !== null) {
            return $this->domains;
        }

        $this->domains = [];

        $lines = file(resource_path('data/disposable-email-domains.txt'), FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $line) {
            $domain = trim($line);

            if ($domain !== '') {
                $this->domains[$domain] = true;
            }
        }

        return $this->domains;
    }
}
