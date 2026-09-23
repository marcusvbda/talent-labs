<?php

namespace App\Filament\Resources\CollectionRuns\Tables;

use App\Actions\Collection\MarkCollectionRunFailed;
use App\Exceptions\CollectionRunException;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\CollectionRun;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CollectionRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['triggeredBy', 'sourceRuns.source']))
            ->columns([
                TextColumn::make('label')
                    ->label('Run')
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('sources')
                    ->label('Sources (ok / failed / total)')
                    ->state(fn (CollectionRun $record): string => "{$record->sources_succeeded} / {$record->sources_failed} / {$record->sources_total}"),

                TextColumn::make('jobs_fetched')
                    ->label('Jobs fetched')
                    ->sortable(),

                TextColumn::make('jobs_new')
                    ->label('New jobs')
                    ->sortable(),

                TextColumn::make('triggeredBy.name')
                    ->label('Triggered by')
                    ->placeholder('—'),

                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('duration')
                    ->label('Finished at')
                    ->state(fn (CollectionRun $record): string => self::formatFinishedAt($record))
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('finished_at', $direction)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('viewJobs')
                    ->label('View jobs')
                    ->url(fn (CollectionRun $record): string => JobPostingResource::getUrl('index', [
                        'tableFilters' => [
                            'collection_run' => ['value' => $record->id],
                        ],
                    ])),
                Action::make('markAsFailed')
                    ->label('Mark as failed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CollectionRun $record): bool => $record->status->isInProgress())
                    ->action(function (CollectionRun $record): void {
                        try {
                            app(MarkCollectionRunFailed::class)->handle($record);

                            Notification::make()
                                ->title("Run #{$record->id} marked as failed")
                                ->success()
                                ->send();
                        } catch (CollectionRunException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->socket(channel: 'collection_runs', event: 'CollectionRunUpdated');
    }

    private static function formatFinishedAt(CollectionRun $record): string
    {
        if ($record->finished_at === null) {
            return '—';
        }

        $duration = $record->started_at !== null
            ? $record->started_at->diff($record->finished_at)->forHumans(['short' => true, 'parts' => 2])
            : null;

        $finishedAt = $record->finished_at->format('j M Y H:i');

        return $duration !== null ? "{$finishedAt} ({$duration})" : $finishedAt;
    }
}
