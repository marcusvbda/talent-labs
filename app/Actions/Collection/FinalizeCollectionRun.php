<?php

namespace App\Actions\Collection;

use App\Enums\CollectionRunStatus;
use App\Enums\SourceRunStatus;
use App\Filament\Resources\CollectionRuns\CollectionRunResource;
use App\Models\CollectionRun;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class FinalizeCollectionRun
{
    /**
     * Close a run once its batch has finished. Safe to call more than once.
     */
    public function handle(int $runId): void
    {
        $run = CollectionRun::with('sourceRuns')->find($runId);

        if ($run === null || ($run->finished_at !== null && ! $run->status->isInProgress())) {
            return;
        }

        $this->applyCounters($run);

        $run->status = match (true) {
            $run->sources_failed === 0 => CollectionRunStatus::Completed,
            $run->sources_succeeded === 0 => CollectionRunStatus::Failed,
            default => CollectionRunStatus::Partial,
        };
        $run->finished_at = now();
        $run->save();

        $user = $run->triggered_by !== null ? User::find($run->triggered_by) : null;

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title("Run #{$run->id} finished — {$run->jobs_new} new jobs from {$run->sources_total} sources".($run->sources_failed > 0 ? " ({$run->sources_failed} failed)" : ''))
            ->color(match ($run->status) {
                CollectionRunStatus::Completed => 'success',
                CollectionRunStatus::Partial => 'warning',
                default => 'danger',
            })
            ->actions([
                Action::make('view')
                    ->label('View run')
                    ->url(CollectionRunResource::getUrl('view', ['record' => $run])),
            ])
            ->sendToDatabase($user, isEventDispatched: true);
    }

    /**
     * Aggregate the run's counters from its (loaded) source runs.
     */
    public function applyCounters(CollectionRun $run): void
    {
        $sourceRuns = $run->sourceRuns;

        $run->sources_succeeded = $sourceRuns->where('status', SourceRunStatus::Completed)->count();
        $run->sources_failed = $sourceRuns->where('status', SourceRunStatus::Failed)->count();
        $run->jobs_fetched = (int) $sourceRuns->sum('jobs_fetched');
        $run->jobs_new = (int) $sourceRuns->sum('jobs_new');
    }
}
