<?php

namespace App\Contacts\Actions;

use App\Contacts\Support\DnsLookup;
use App\Contacts\Support\SmtpProbe;
use App\Enums\ContactConfidence;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Models\Company;
use Illuminate\Support\Str;

class DiscoverCompanyContacts
{
    /**
     * Role mailbox local parts tried for every company.
     */
    public const array ALIASES = ['careers', 'jobs', 'hr', 'talent', 'recruiting', 'people'];

    public function __construct(
        private DnsLookup $dns,
        private SmtpProbe $smtp,
    ) {}

    /**
     * Probe the company's mail server for role aliases in a single handshake-only
     * session and store the ones that can be addressed, with their confidence.
     */
    public function handle(Company $company): void
    {
        $domain = (string) $company->domain;

        if ($company->domain_status !== DomainStatus::Found || $domain === '') {
            $company->contact_status = ContactStatus::NoDomain;
            $company->contact_checked_at = now();
            $company->save();

            return;
        }

        if ($company->contact_status !== ContactStatus::Pending) {
            return;
        }

        $host = $this->dns->mxHosts($domain)[0] ?? $domain;

        $probeAddress = 'nonexistent-'.Str::lower(Str::random(8)).'@'.$domain;

        $aliasAddresses = [];

        foreach (self::ALIASES as $alias) {
            $aliasAddresses[$alias] = "{$alias}@{$domain}";
        }

        $result = $this->smtp->probe($host, [$probeAddress, ...array_values($aliasAddresses)]);

        $probeCode = $result->codes[$probeAddress] ?? null;

        $isCatchAll = match (true) {
            ! $result->connected, ! $result->sessionOk, $probeCode === null => null,
            $this->isAccepted($probeCode) => true,
            $probeCode >= 500 && $probeCode < 600 => false,
            default => null,
        };

        $stored = 0;

        foreach ($aliasAddresses as $alias => $address) {
            $confidence = $this->confidenceFor($isCatchAll, $result->codes[$address] ?? null);

            if ($confidence === null) {
                continue;
            }

            $company->contacts()->updateOrCreate(
                ['local_part' => $alias],
                ['email' => $address, 'confidence' => $confidence, 'checked_at' => now()],
            );

            $stored++;
        }

        $company->is_catch_all = $isCatchAll;
        $company->contact_status = $stored > 0 ? ContactStatus::Found : ContactStatus::NotFound;
        $company->contact_checked_at = now();
        $company->save();
    }

    /**
     * Confidence to store for an alias, or null when the alias must be skipped.
     */
    private function confidenceFor(?bool $isCatchAll, ?int $code): ?ContactConfidence
    {
        if ($isCatchAll === null) {
            return ContactConfidence::MxOnly;
        }

        if (! $this->isAccepted($code)) {
            return null;
        }

        return $isCatchAll ? ContactConfidence::CatchAll : ContactConfidence::SmtpVerified;
    }

    private function isAccepted(?int $code): bool
    {
        return $code !== null && $code >= 200 && $code < 300;
    }
}
