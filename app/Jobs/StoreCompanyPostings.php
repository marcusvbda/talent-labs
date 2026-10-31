<?php

namespace App\Jobs;

use App\Actions\Collection\UpsertJobPostings;
use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Contacts\Actions\RunCompanyDiscovery;
use App\Contacts\Jobs\SyncPublishedContacts;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\SourceRun;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;
use Throwable;

/**
 * Validates a still-pending company found in a collection run and stores its new postings
 * only if the company turns out verified for sending.
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
        return [(new WithoutOverlapping((string) $this->companyId))->releaseAfter(15)->expireAfter(120)];
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

            // Not verified (closed, still pending or inconclusive): nothing is stored.
            if (! $company->isVerifiedForSending()) {
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
        $externalIds = array_map('strval', array_column($this->rows, 'external_id'));

        $stored = JobPosting::query()
            ->where('source_id', $sourceRun->source_id)
            ->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $newExternalIds = array_values(array_diff($externalIds, $stored));

        app(UpsertJobPostings::class)->handle(
            array_map(fn (array $row): array => [...$row, 'company_id' => $company->id], $this->rows),
        );

        if ($newExternalIds !== []) {
            JobPosting::query()
                ->where('source_id', $sourceRun->source_id)
                ->whereIn('external_id', $newExternalIds)
                ->pluck('id')
                ->each(fn (int $id) => ExtractJobPostingProfileJob::dispatch($id));
        }

        // Other groups of the run add to the same counter; saving fires the run's realtime hook.
        DB::transaction(function () use ($newExternalIds): void {
            $sourceRun = SourceRun::query()->lockForUpdate()->find($this->sourceRunId);

            if ($sourceRun === null) {
                return;
            }

            $sourceRun->jobs_new += count($newExternalIds);
            $sourceRun->save();
        });

        // The upsert skips model events: announce this company's postings once.
        try {
            RealtimeEvent::dispatch('job_postings', 'JobPostingsUpdated', ['source_run_id' => $this->sourceRunId]);
        } catch (BroadcastException $e) {
            report($e);
        }

        SyncPublishedContacts::dispatchForPostings($sourceRun->source_id, $newExternalIds);
    }
}
