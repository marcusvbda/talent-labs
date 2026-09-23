<?php

namespace App\Actions\Collection;

use App\Enums\CollectionRunStatus;
use App\Enums\SourceRunStatus;
use App\Exceptions\CollectionRunException;
use App\Jobs\FetchJobsFromSource;
use App\Models\CollectionRun;
use App\Models\Source;
use App\Models\SourceRun;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartCollectionRun
{
    /**
     * Start a collection run over every active source.
     *
     * @throws CollectionRunException
     */
    public function handle(User $triggeredBy): CollectionRun
    {
        $lock = Cache::lock('collection-runs:start', 10);

        if (! $lock->get()) {
            throw new CollectionRunException('A collection run is already in progress.');
        }

        try {
            return DB::transaction(function () use ($triggeredBy): CollectionRun {
                if (CollectionRun::inProgress()->exists()) {
                    throw new CollectionRunException('A collection run is already in progress.');
                }

                $sources = Source::active()->orderBy('id')->get();

                if ($sources->isEmpty()) {
                    throw new CollectionRunException('There are no active sources to collect.');
                }

                $run = CollectionRun::create([
                    'status' => CollectionRunStatus::Pending,
                    'triggered_by' => $triggeredBy->id,
                    'sources_total' => $sources->count(),
                ]);

                $sourceRuns = $sources->map(fn (Source $source): SourceRun => $run->sourceRuns()->create([
                    'source_id' => $source->id,
                    'status' => SourceRunStatus::Pending,
                ]));

                $runId = $run->id;

                // Static closure capturing only the run id: nothing else is serialized into the batch.
                $batch = Bus::batch($sourceRuns->map(fn (SourceRun $sourceRun): FetchJobsFromSource => new FetchJobsFromSource($sourceRun->id))->all())
                    ->onQueue('collection')
                    ->allowFailures()
                    ->finally(static fn (Batch $batch) => app(FinalizeCollectionRun::class)->handle($runId))
                    ->name("collection-run-{$runId}")
                    ->dispatch();

                $run->update([
                    'batch_id' => $batch->id,
                    'status' => CollectionRunStatus::Running,
                    'started_at' => now(),
                ]);

                return $run;
            });
        } finally {
            $lock->release();
        }
    }
}
