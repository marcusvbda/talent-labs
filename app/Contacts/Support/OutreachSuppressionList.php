<?php

namespace App\Contacts\Support;

use App\Models\OutreachSuppression;
use App\Support\RegistrableDomain;
use Illuminate\Support\Str;

class OutreachSuppressionList
{
    /**
     * True when a confirmed suppression covers the domain (matched on its registrable domain).
     */
    public function coversDomain(string $domain): bool
    {
        $domain = RegistrableDomain::of($domain) ?? strtolower(trim($domain));

        if ($domain === '') {
            return false;
        }

        return OutreachSuppression::query()
            ->whereNotNull('confirmed_at')
            ->where('domain', $domain)
            ->exists();
    }

    /**
     * True when a confirmed suppression covers the exact address or its domain.
     */
    public function covers(string $email): bool
    {
        $email = strtolower(trim($email));

        $suppressed = OutreachSuppression::query()
            ->whereNotNull('confirmed_at')
            ->where('email', $email)
            ->exists();

        return $suppressed || (str_contains($email, '@') && $this->coversDomain(Str::afterLast($email, '@')));
    }
}
