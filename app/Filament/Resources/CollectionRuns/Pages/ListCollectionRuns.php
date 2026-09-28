<?php

namespace App\Filament\Resources\CollectionRuns\Pages;

use App\Filament\Actions\CollectJobsNowAction;
use App\Filament\Resources\CollectionRuns\CollectionRunResource;
use Filament\Resources\Pages\ListRecords;

class ListCollectionRuns extends ListRecords
{
    protected static string $resource = CollectionRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CollectJobsNowAction::make()->label('Collect jobs now'),
        ];
    }
}
