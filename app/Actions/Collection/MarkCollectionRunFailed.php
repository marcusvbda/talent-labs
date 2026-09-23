<?php

namespace App\Actions\Collection;

use App\Enums\CollectionRunStatus;
use App\Enums\SourceRunStatus;
use App\Exceptions\CollectionRunException;
use App\Models\CollectionRun;
use Illuminate\Support\Facades\Bus;

class MarkCollectionRunFailed
{
    public function __construct(private FinalizeCollectionRun $finalize) {}

    /**
     * Force a stuck run (and its unfinished source runs) to failed and cancel its batch.
     *
     * @throws CollectionRunException
     */
    public function handle(CollectionRun $run): void
    {
        if (! $run->status->isInProgress()) {
            throw new CollectionRunException('This run is not in progress.');
        }

        $unfinished = $run->sourceRuns()
            ->whereIn('status', [SourceRunStatus::Pending, SourceRunStatus::Running])
            ->get();

        // Saved one by one so model events broadcast the change.
        foreach ($unfinished as $sourceRun) {
            $sourceRun->status = SourceRunStatus::Failed;
            $sourceRun->error_message = 'Marked as failed by an admin.';
            $sourceRun->finished_at = now();
            $sourceRun->save();
        }

        $run->load('sourceRuns');
        $this->finalize->applyCounters($run);
        $run->status = CollectionRunStatus::Failed;
        $run->finished_at = now();
        $run->save();

        if ($run->batch_id !== null) {
            Bus::findBatch($run->batch_id)?->cancel();
        }
    }
}
