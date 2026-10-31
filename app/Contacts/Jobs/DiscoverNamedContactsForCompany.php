<?php

namespace App\Contacts\Jobs;

use App\Contacts\Actions\DiscoverNamedContacts;
use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverNamedContactsForCompany implements ShouldBeUnique, ShouldQueue
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

    public function uniqueId(): string
    {
        return (string) $this->companyId;
    }

    public function handle(DiscoverNamedContacts $discovery): void
    {
        // Shared with domain/contact discovery: never two probes against one company at once.
        $lock = Cache::lock("contacts:company:{$this->companyId}", 90);

        if (! $lock->get()) {
            return;
        }

        try {
            $company = Company::find($this->companyId);

            if ($company === null) {
                return;
            }

            $discovery->handle($company);
        } catch (Throwable $e) {
            // No personal data: the exception message may hold names or addresses.
            Log::warning('DiscoverNamedContactsForCompany failed', [
                'company_id' => $this->companyId,
                'exception' => $e::class,
            ]);
        } finally {
            $lock->release();
        }
    }
}
