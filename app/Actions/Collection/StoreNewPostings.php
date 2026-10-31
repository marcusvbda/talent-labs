<?php

namespace App\Actions\Collection;

use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\SourceRun;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

class StoreNewPostings
{
    public function __construct(private UpsertJobPostings $upsert) {}

    /**
     * Store a source run's new postings of the company: queue the profile extraction of the ones
     * that didn't exist yet and add them to the run's jobs_new.
     *
     * @param  list<array<string, mixed>>  $rows  new postings in column form, without company_id
     * @return array<string, int> posting id by external id, for these rows
     */
    public function handle(Company $company, SourceRun $sourceRun, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $externalIds = array_map('strval', array_column($rows, 'external_id'));

        $stored = JobPosting::query()
            ->where('source_id', $sourceRun->source_id)
            ->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $newExternalIds = array_values(array_diff($externalIds, $stored));

        $this->upsert->handle(
            array_map(fn (array $row): array => [...$row, 'company_id' => $company->id], $rows),
        );

        /** @var array<string, int> $ids */
        $ids = JobPosting::query()
            ->where('source_id', $sourceRun->source_id)
            ->whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id')
            ->mapWithKeys(fn (int $id, mixed $externalId): array => [(string) $externalId => $id])
            ->all();

        foreach ($newExternalIds as $externalId) {
            if (isset($ids[$externalId])) {
                ExtractJobPostingProfileJob::dispatch($ids[$externalId]);
            }
        }

        // Other groups of the run add to the same counter; saving fires the run's realtime hook.
        DB::transaction(function () use ($sourceRun, $newExternalIds): void {
            $locked = SourceRun::query()->lockForUpdate()->find($sourceRun->id);

            if ($locked === null) {
                return;
            }

            $locked->jobs_new += count($newExternalIds);
            $locked->save();
        });

        // The upsert skips model events: announce this company's postings once.
        try {
            RealtimeEvent::dispatch('job_postings', 'JobPostingsUpdated', ['source_run_id' => $sourceRun->id]);
        } catch (BroadcastException $e) {
            report($e);
        }

        return $ids;
    }
}
