<?php

namespace App\Contacts\Actions;

use App\Contacts\Support\DnsLookup;
use App\Enums\DomainStatus;
use App\Models\Company;
use App\Support\RegistrableDomain;
use Illuminate\Support\Str;

class ResolveCompanyDomain
{
    public function __construct(private DnsLookup $dns) {}

    /**
     * Try the website domain the source gave us, then "<slug>.com"; the first candidate
     * that resolves is marked found.
     */
    public function handle(Company $company): void
    {
        if ($company->domain_status !== DomainStatus::Pending) {
            return;
        }

        $websites = [];

        $recent = $company->jobPostings()
            ->whereNotNull('company_website')
            ->latest('id')
            ->limit(3)
            ->pluck('company_website');

        foreach ($recent as $website) {
            if (is_string($website)) {
                $websites[] = $website;
            }
        }

        $found = null;

        foreach ($this->candidates($websites, $company->normalized_name) as $candidate) {
            if ($this->resolves($candidate)) {
                $found = $candidate;

                break;
            }
        }

        $company->domain = $found;
        $company->domain_status = $found !== null ? DomainStatus::Found : DomainStatus::NotFound;
        $company->domain_checked_at = now();
        $company->save();
    }

    /**
     * Candidate domains in priority order: the registrable domains of the websites the source gave
     * us (most recent first), then "<slug>.com".
     *
     * @param  list<string>  $websites
     * @return list<string>
     */
    private function candidates(array $websites, string $normalizedName): array
    {
        $candidates = [];

        foreach ($websites as $website) {
            $domain = RegistrableDomain::of($website);

            if ($domain !== null && ! in_array($domain, $candidates, true)) {
                $candidates[] = $domain;
            }
        }

        $slug = Str::slug($normalizedName, '');

        if ($slug !== '' && ! in_array($slug.'.com', $candidates, true)) {
            $candidates[] = $slug.'.com';
        }

        return $candidates;
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
