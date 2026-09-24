<?php

namespace App\Contacts\Jobs;

use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Contacts\Actions\ResolveCompanyForPosting;
use App\Contacts\Actions\RunCompanyDiscovery;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Enums\OutreachStatus;
use App\Models\JobPosting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscoverContactsForPosting implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    public function __construct(public int $jobPostingId)
    {
        $this->onQueue('contacts');
    }

    public function handle(ResolveCompanyForPosting $resolveCompany, RunCompanyDiscovery $runDiscovery): void
    {
        try {
            $posting = JobPosting::find($this->jobPostingId);

            if ($posting === null) {
                return;
            }

            $company = $resolveCompany->handle($posting);

            if (
                $company->wasRecentlyCreated
                || $company->domain_status === DomainStatus::Pending
                || $company->contact_status === ContactStatus::Pending
            ) {
                $runDiscovery->handle($company);
            } elseif (
                $company->outreach_status === OutreachStatus::Verified
                && JobPosting::query()->whereKey($posting->id)->needingProfile()->exists()
            ) {
                ExtractJobPostingProfileJob::dispatch($posting->id);
            }
        } catch (Throwable $e) {
            Log::warning('DiscoverContactsForPosting failed', [
                'job_posting_id' => $this->jobPostingId,
                'exception' => $e,
            ]);
        }
    }
}
