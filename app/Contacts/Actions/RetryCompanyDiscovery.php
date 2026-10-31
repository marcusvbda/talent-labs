<?php

namespace App\Contacts\Actions;

use App\Contacts\Jobs\DiscoverContactsForCompany;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Enums\OutreachStatus;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class RetryCompanyDiscovery
{
    /**
     * Drop the company's alias contacts, reset it to pending and queue a fresh discovery.
     */
    public function handle(Company $company): void
    {
        DB::transaction(function () use ($company): void {
            // Only probed aliases are rediscovered; published and named contacts survive the retry.
            $company->contacts()->where('source', ContactSource::AliasProbe)->delete();

            $company->domain_status = DomainStatus::Pending;
            $company->contact_status = ContactStatus::Pending;
            $company->outreach_status = OutreachStatus::Pending;
            $company->is_catch_all = null;
            $company->domain = null;
            // Clear the attempt timestamps so the pending cooldown doesn't skip this retry.
            $company->domain_checked_at = null;
            $company->contact_checked_at = null;

            // save() emits CompanyUpdated, which also covers the deleted contacts.
            $company->save();
        });

        // Dispatch only once the reset is committed.
        DiscoverContactsForCompany::dispatch($company->id);
    }
}
