<?php

namespace App\Contacts\Actions;

use App\Contacts\Jobs\DiscoverContactsForCompany;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class RetryCompanyDiscovery
{
    /**
     * Drop the company's contacts, reset it to pending and queue a fresh discovery.
     */
    public function handle(Company $company): void
    {
        DB::transaction(function () use ($company): void {
            $company->contacts()->delete();

            $company->domain_status = DomainStatus::Pending;
            $company->contact_status = ContactStatus::Pending;
            $company->is_catch_all = null;
            $company->domain = null;

            // save() emits CompanyUpdated, which also covers the deleted contacts.
            $company->save();
        });

        // Dispatch only once the reset is committed.
        DiscoverContactsForCompany::dispatch($company->id);
    }
}
