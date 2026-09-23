<?php

namespace App\Filament\Resources\CollectionRuns\Pages;

use App\Actions\Collection\MarkCollectionRunFailed;
use App\Exceptions\CollectionRunException;
use App\Filament\Resources\CollectionRuns\CollectionRunResource;
use App\Models\CollectionRun;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewCollectionRun extends ViewRecord
{
    protected static string $resource = CollectionRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsFailed')
                ->label('Mark as failed')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRun()->status->isInProgress())
                ->action(function (): void {
                    $run = $this->getRun();

                    try {
                        app(MarkCollectionRunFailed::class)->handle($run);

                        Notification::make()
                            ->title("Run #{$run->id} marked as failed")
                            ->success()
                            ->send();
                    } catch (CollectionRunException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getRun(): CollectionRun
    {
        /** @var CollectionRun $record */
        $record = $this->getRecord();

        return $record;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.collection-runs.realtime-listener')
                    ->viewData(['record' => $this->getRecord()]),
                $this->getInfolistContentComponent(),
                $this->getRelationManagersContentComponent(),
            ]);
    }
}
