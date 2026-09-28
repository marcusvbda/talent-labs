<?php

namespace App\Filament\Actions;

use App\Actions\Collection\StartCollectionRun;
use App\Exceptions\CollectionRunException;
use App\Models\CollectionRun;
use App\Models\Source;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CollectJobsNowAction
{
    public static function make(string $name = 'collectJobsNow'): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'This will collect jobs from '.Source::active()->count().' active sources.')
            ->disabled(fn (): bool => CollectionRun::inProgress()->exists() || Source::active()->count() === 0)
            ->tooltip(function (): ?string {
                if (CollectionRun::inProgress()->exists()) {
                    return 'A collection run is already in progress.';
                }

                if (Source::active()->count() === 0) {
                    return 'There are no active sources.';
                }

                return null;
            })
            ->action(function (): void {
                try {
                    $run = app(StartCollectionRun::class)->handle(auth()->user());

                    Notification::make()
                        ->title("Run #{$run->id} started")
                        ->body("Collecting from {$run->sources_total} sources.")
                        ->success()
                        ->send();
                } catch (CollectionRunException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
