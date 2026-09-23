<?php

namespace App\Jobs;

use App\Collection\Data\JobPostingData;
use App\Contacts\Jobs\DiscoverContactsForPosting;
use App\Enums\SourceRunStatus;
use App\Models\JobPosting;
use App\Models\SourceRun;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;
use Throwable;

class FetchJobsFromSource implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    private const CHUNK_SIZE = 200;

    /**
     * Columns refreshed when a posting already exists. Never `collection_run_id`,
     * `first_seen_at` or `created_at`: those belong to the run that first found it.
     */
    private const MUTABLE_COLUMNS = [
        'title', 'company_name', 'location', 'is_remote', 'department', 'employment_type',
        'url', 'apply_url', 'description_html', 'description_text', 'published_at', 'raw',
        'last_seen_run_id', 'last_seen_at', 'updated_at',
    ];

    public function __construct(public int $sourceRunId) {}

    public function handle(): void
    {
        $sourceRun = SourceRun::with('source')->find($this->sourceRunId);

        if ($sourceRun === null || $this->batch()?->cancelled()) {
            return;
        }

        $sourceRun->status = SourceRunStatus::Running;
        $sourceRun->started_at = now();
        $sourceRun->save();

        $source = $sourceRun->source;

        try {
            // Keyed by external id so the last occurrence wins: one ON CONFLICT
            // statement can't touch the same row twice.
            $items = [];

            foreach ($source->adapter->adapter()->fetch($source) as $item) {
                $items[$item->externalId] = $item;
            }

            $jobsNew = 0;
            $newExternalIds = [];

            foreach (array_chunk($items, self::CHUNK_SIZE, true) as $chunk) {
                $existing = JobPosting::query()
                    ->where('source_id', $source->id)
                    ->whereIn('external_id', array_map('strval', array_keys($chunk)))
                    ->pluck('external_id')
                    ->all();

                $chunkNewIds = array_diff(array_map('strval', array_keys($chunk)), $existing);
                $jobsNew += count($chunkNewIds);
                array_push($newExternalIds, ...$chunkNewIds);

                $now = now();

                $rows = array_map(fn (JobPostingData $item): array => [
                    'source_id' => $source->id,
                    'collection_run_id' => $sourceRun->collection_run_id,
                    'last_seen_run_id' => $sourceRun->collection_run_id,
                    'external_id' => $item->externalId,
                    'title' => $item->title,
                    'company_name' => $item->companyName,
                    'location' => $item->location,
                    'is_remote' => $item->isRemote,
                    'department' => $item->department,
                    'employment_type' => $item->employmentType,
                    'url' => $item->url,
                    'apply_url' => $item->applyUrl,
                    'description_html' => $item->descriptionHtml,
                    'description_text' => $item->descriptionText,
                    'published_at' => $item->publishedAt?->setTimezone(config('app.timezone')),
                    // Query-builder upserts skip Eloquent casts.
                    'raw' => json_encode($item->raw),
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_values($chunk));

                JobPosting::upsert($rows, ['source_id', 'external_id'], self::MUTABLE_COLUMNS);
            }

            $sourceRun->status = SourceRunStatus::Completed;
            $sourceRun->jobs_fetched = count($items);
            $sourceRun->jobs_new = $jobsNew;
            $sourceRun->finished_at = now();
            $sourceRun->save();

            $source->last_run_at = now();
            $source->last_run_status = SourceRunStatus::Completed;
            $source->save();
        } catch (Throwable $e) {
            $this->markFailed($sourceRun, $e);

            return;
        }

        // The upsert skips model events: announce the new/updated postings once.
        try {
            RealtimeEvent::dispatch('job_postings', 'JobPostingsUpdated', ['source_run_id' => $sourceRun->id]);
        } catch (BroadcastException $e) {
            report($e);
        }

        try {
            foreach (array_chunk($newExternalIds, self::CHUNK_SIZE) as $chunk) {
                JobPosting::query()
                    ->where('source_id', $source->id)
                    ->whereIn('external_id', $chunk)
                    ->pluck('id')
                    ->each(fn (int $id) => DiscoverContactsForPosting::dispatch($id));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Covers timeouts and killed workers, where handle() never reached its catch.
     */
    public function failed(Throwable $e): void
    {
        $sourceRun = SourceRun::with('source')->find($this->sourceRunId);

        if ($sourceRun === null || $sourceRun->finished_at !== null) {
            return;
        }

        $this->markFailed($sourceRun, $e);
    }

    private function markFailed(SourceRun $sourceRun, Throwable $e): void
    {
        $sourceRun->status = SourceRunStatus::Failed;
        $sourceRun->error_message = Str::limit($e->getMessage(), 1000);
        $sourceRun->finished_at = now();
        $sourceRun->save();

        $sourceRun->source->last_run_at = now();
        $sourceRun->source->last_run_status = SourceRunStatus::Failed;
        $sourceRun->source->save();

        Log::warning('FetchJobsFromSource failed', [
            'source_run_id' => $sourceRun->id,
            'exception' => $e,
        ]);
    }
}
