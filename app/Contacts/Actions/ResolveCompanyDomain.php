<?php

namespace App\Contacts\Actions;

use App\Contacts\Support\DnsLookup;
use App\Enums\DomainStatus;
use App\Models\Company;
use App\Support\RegistrableDomain;
use Illuminate\Support\Str;

class ResolveCompanyDomain
{
    private const int RETRY_PAUSE_MICROSECONDS = 400_000;

    public function __construct(private DnsLookup $dns) {}

    /**
     * Try the website domain the source gave us, then "<slug>.com"; the first candidate
     * with an MX host is marked found. A DNS failure leaves the company pending.
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

        $candidates = $this->candidates($websites, $company->normalized_name);

        [$found, $lookupFailed] = $this->firstResolving($candidates);

        if ($found === null && $candidates !== []) {
            // A miss may be a resolver hiccup: look once more before concluding.
            usleep(self::RETRY_PAUSE_MICROSECONDS);

            [$found, $lookupFailed] = $this->firstResolving($candidates);
        }

        $gaveUp = $company->domain_checked_at !== null
            && $company->domain_checked_at->lessThanOrEqualTo(now()->subDays(RunCompanyDiscovery::PENDING_GIVE_UP_DAYS));

        if ($lookupFailed && ! $gaveUp) {
            // The resolver failed instead of answering, for the whole list or for a higher-priority candidate
            // than the one that resolved: stay pending and try again later.
            if ($company->domain_checked_at === null) {
                $company->domain_checked_at = now();
                $company->save();
            }

            return;
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

    /**
     * The first candidate that resolves, and whether any lookup failed along the way.
     *
     * @param  list<string>  $candidates
     * @return array{0: string|null, 1: bool}
     */
    private function firstResolving(array $candidates): array
    {
        $lookupFailed = false;

        foreach ($candidates as $candidate) {
            $resolves = $this->resolves($candidate);

            if ($resolves === true) {
                return [$candidate, $lookupFailed];
            }

            $lookupFailed = $lookupFailed || $resolves === null;
        }

        return [null, $lookupFailed];
    }

    /**
     * A domain counts only when it can receive mail: at least one non-null MX host. Null when the
     * DNS lookup itself failed.
     */
    private function resolves(string $domain): ?bool
    {
        $hosts = $this->dns->mxHostsOrNull($domain);

        return $hosts === null ? null : $hosts !== [];
    }
}
