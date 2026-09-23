<?php

namespace App\Contacts\Jobs;

use App\Contacts\Actions\RunCompanyDiscovery;
use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverContactsForCompany implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    public function __construct(public int $companyId)
    {
        $this->onQueue('contacts');
    }

    public function handle(RunCompanyDiscovery $discovery): void
    {
        try {
            $company = Company::find($this->companyId);

            if ($company === null) {
                return;
            }

            $discovery->handle($company);
        } catch (Throwable $e) {
            Log::warning('DiscoverContactsForCompany failed', [
                'company_id' => $this->companyId,
                'exception' => $e,
            ]);
        }
    }
}
