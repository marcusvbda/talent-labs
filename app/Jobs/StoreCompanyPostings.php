<?php

namespace App\Jobs;

use App\Actions\Collection\StoreNewPostings;
use App\Contacts\Actions\RunCompanyDiscovery;
use App\Contacts\Jobs\SyncPublishedContacts;
use App\Models\Company;
use App\Models\SourceRun;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validates a still-pending company found in a collection run and stores its new postings
 * if the company turns out verified for sending; otherwise hands the ones publishing an email
 * to StorePublishedPostings.
 */
class StoreCompanyPostings implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Every release by WithoutOverlapping counts as an attempt: these cover the wait
     * (5 x 15s) for another group of the same company. Failures are caught in handle().
     */
    public int $tries = 6;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    /**
     * The extra tries are only for lock waits, never to redo a timed-out probe.
     */
    public bool $failOnTimeout = true;

    /**
     * @param  list<array<string, mixed>>  $rows  new postings in column form, without company_id
     * @param  list<string>  $websites  company websites seen in these postings
     */
    public function __construct(
        public int $sourceRunId,
        public int $companyId,
        public array $rows,
        public array $websites,
    ) {}

    /**
     * Two groups of the same company never probe in parallel.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->companyId))->releaseAfter(15)->expireAfter(120)->shared()];
    }

    public function handle(RunCompanyDiscovery $discovery): void
    {
        try {
            if ($this->batch()?->cancelled()) {
                return;
            }

            $company = Company::find($this->companyId);
            $sourceRun = SourceRun::find($this->sourceRunId);

            if ($company === null || $sourceRun === null) {
                return;
            }

            if (! $company->isVerifiedForSending() && ! $company->isClosedForSending() && ! $discovery->isCoolingDown($company)) {
                $discovery->handle($company, $this->websites);
                $company->refresh();
            }

            // Not verified (closed, still pending or inconclusive): only postings publishing an email may be stored.
            if (! $company->isVerifiedForSending()) {
                $this->deferPublished();

                return;
            }

            $this->store($company, $sourceRun);
        } catch (Throwable $e) {
            Log::warning('StoreCompanyPostings failed', [
                'source_run_id' => $this->sourceRunId,
                'company_id' => $this->companyId,
                'exception' => $e,
            ]);
        }
    }

    private function store(Company $company, SourceRun $sourceRun): void
    {
        $ids = app(StoreNewPostings::class)->handle($company, $sourceRun, $this->rows);

        SyncPublishedContacts::dispatchForPostings($sourceRun->source_id, array_map('strval', array_keys($ids)));
    }

    /**
     * Rows publishing an email may still be stored on that email: probed in their own job,
     * the discovery above may already have used most of this one's time.
     */
    private function deferPublished(): void
    {
        $rows = array_values(array_filter($this->rows, fn (array $row): bool => StorePublishedPostings::emailsOfRow($row) !== []));

        if ($rows === []) {
            return;
        }

        $job = new StorePublishedPostings($this->sourceRunId, $this->companyId, $rows);
        $batch = $this->batch();

        if ($batch !== null) {
            $batch->add([$job]);
        } else {
            dispatch($job)->onQueue('collection');
        }
    }
}
