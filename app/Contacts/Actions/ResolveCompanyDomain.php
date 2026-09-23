<?php

namespace App\Contacts\Actions;

use App\Contacts\Support\DnsLookup;
use App\Enums\DomainStatus;
use App\Models\Company;
use Illuminate\Support\Str;

class ResolveCompanyDomain
{
    public function __construct(private DnsLookup $dns) {}

    /**
     * Guess the company's domain as "<slug>.com" and mark it found when it resolves.
     */
    public function handle(Company $company): void
    {
        if ($company->domain_status !== DomainStatus::Pending) {
            return;
        }

        $slug = Str::slug($company->normalized_name, '');
        $candidate = $slug !== '' ? $slug.'.com' : null;

        $found = $candidate !== null && $this->resolves($candidate);

        $company->domain = $found ? $candidate : null;
        $company->domain_status = $found ? DomainStatus::Found : DomainStatus::NotFound;
        $company->domain_checked_at = now();
        $company->save();
    }

    private function resolves(string $domain): bool
    {
        if ($this->dns->mxHosts($domain) !== []) {
            return true;
        }

        if ($this->dns->hasOnlyNullMx($domain)) {
            return false;
        }

        return $this->dns->hasAddress($domain);
    }
}
