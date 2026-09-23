<?php

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSource extends EditRecord
{
    protected static string $resource = SourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (Source $record): bool => $record->hasHistory())
                ->tooltip(fn (Source $record): ?string => $record->hasHistory()
                    ? 'This source has collection history — deactivate it instead.'
                    : null),
        ];
    }
}
