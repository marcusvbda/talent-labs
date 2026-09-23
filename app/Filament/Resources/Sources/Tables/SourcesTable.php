<?php

namespace App\Filament\Resources\Sources\Tables;

use App\Enums\SourceAdapter;
use App\Models\Source;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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

                DeleteAction::make()
                    ->disabled(fn (Source $record): bool => $record->hasHistory())
                    ->tooltip(fn (Source $record): ?string => $record->hasHistory()
                        ? 'This source has collection history — deactivate it instead.'
                        : null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(self::deleteExcludingHistory(...)),
                ]),
            ])
            ->socket(channel: 'sources', event: 'SourceUpdated');
    }

    /**
     * @param  EloquentCollection<int, Source>  $records
     */
    private static function deleteExcludingHistory(EloquentCollection $records): void
    {
        $deletable = $records->reject(fn (Source $record): bool => $record->hasHistory());
        $skipped = $records->count() - $deletable->count();

        $deletable->each(fn (Source $record) => $record->delete());

        if ($skipped > 0) {
            Notification::make()
                ->title($skipped === 1
                    ? '1 source was skipped because it has collection history.'
                    : "{$skipped} sources were skipped because they have collection history.")
                ->warning()
                ->send();
        }
    }
}
