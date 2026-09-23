<?php

namespace App\Filament\Resources\CollectionRuns\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourceRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'sourceRuns';

    protected static ?string $title = 'Source runs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('source'))
            ->columns([
                TextColumn::make('source.name')
                    ->label('Source'),

                TextColumn::make('source.adapter')
                    ->badge(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('jobs_fetched'),

                TextColumn::make('jobs_new')
                    ->label('New jobs'),

                TextColumn::make('error_message')
                    ->wrap()
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state),

                TextColumn::make('started_at')
                    ->dateTime(),

                TextColumn::make('finished_at')
                    ->dateTime(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->socket(channel: 'collection_run_'.$this->getOwnerRecord()->getKey(), event: 'CollectionRunUpdated');
    }
}
