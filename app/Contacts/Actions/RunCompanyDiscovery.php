<?php

namespace App\Contacts\Actions;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunCompanyDiscovery
{
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
}
