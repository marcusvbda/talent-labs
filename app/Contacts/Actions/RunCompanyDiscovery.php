<?php

namespace App\Contacts\Actions;

use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunCompanyDiscovery
{
    /**
     * Hours a still-pending company waits after an inconclusive attempt before it is probed again.
     */
    public const int PENDING_COOLDOWN_HOURS = 6;

    /**
     * Days a company may stay inconclusive (counted from its first inconclusive attempt) before it is closed.
     */
    public const int PENDING_GIVE_UP_DAYS = 7;

    public function __construct(
        private ResolveCompanyDomain $resolveDomain,
        private DiscoverCompanyContacts $discoverContacts,
    ) {}

    /**
     * Resolve the domain and discover contacts for a company, one worker at a time.
     */
    public function handle(Company $company): void
    {
        $lock = Cache::lock("contacts:company:{$company->id}", 90);

        // Another worker owns this company: no parallel probes.
        if (! $lock->get()) {
            return;
        }

        try {
            $company->refresh();

            if ($this->isCoolingDown($company)) {
                return;
            }

            $this->resolveDomain->handle($company);
            $this->discoverContacts->handle($company);
        } catch (Throwable $e) {
            Log::warning('Contact discovery failed', [
                'company_id' => $company->id,
                'exception' => $e,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether a still-pending company had an inconclusive attempt within the cooldown window.
     */
    public function isCoolingDown(Company $company): bool
    {
        $pending = $company->domain_status === DomainStatus::Pending
            || $company->contact_status === ContactStatus::Pending;

        return $pending
            && $company->contact_checked_at !== null
            && $company->contact_checked_at->greaterThan(now()->subHours(self::PENDING_COOLDOWN_HOURS));
    }
}
