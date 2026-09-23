<?php

namespace App\Filament\Resources\CollectionRuns\Schemas;

use App\Models\CollectionRun;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CollectionRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('label')
                    ->label('Run'),

                TextEntry::make('status')
                    ->badge(),

                TextEntry::make('sources')
                    ->label('Sources (ok / failed / total)')
                    ->state(fn (CollectionRun $record): string => "{$record->sources_succeeded} / {$record->sources_failed} / {$record->sources_total}"),

                TextEntry::make('jobs_fetched')
                    ->label('Jobs fetched'),

                TextEntry::make('jobs_new')
                    ->label('New jobs'),

                TextEntry::make('triggeredBy.name')
                    ->label('Triggered by')
                    ->placeholder('—'),

                TextEntry::make('started_at')
                    ->dateTime(),

                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
