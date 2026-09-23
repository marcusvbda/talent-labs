<?php

namespace App\Filament\Resources\Sources\Tables;

use App\Enums\SourceAdapter;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('adapter')
                    ->badge()
                    ->sortable(),

                TextColumn::make('identifier')
                    ->searchable()
                    ->placeholder('—'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('interval_minutes')
                    ->label('Interval (min)')
                    ->sortable(),

                TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('last_run_status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('adapter')
                    ->options(SourceAdapter::class),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->socket(channel: 'sources', event: 'SourceUpdated');
    }
}
