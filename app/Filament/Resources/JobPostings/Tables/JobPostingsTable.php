<?php

namespace App\Filament\Resources\JobPostings\Tables;

use App\Enums\SourceAdapter;
use App\Models\CollectionRun;
use App\Models\JobPosting;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobPostingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['collectionRun', 'source']))
            ->defaultGroup(
                Group::make('collection_run_id')
                    ->label('Run')
                    ->getTitleFromRecordUsing(fn (JobPosting $record): string => $record->collectionRun->label)
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('collection_run_id', 'desc'))
            )
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable(),

                TextColumn::make('location')
                    ->placeholder('—'),

                IconColumn::make('is_remote')
                    ->label('Remote')
                    ->boolean(),

                TextColumn::make('source.name')
                    ->label('Source'),

                TextColumn::make('source.adapter')
                    ->label('Adapter')
                    ->badge(),

                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('first_seen_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('collection_run')
                    ->label('Run')
                    ->options(fn (): array => CollectionRun::query()
                        ->latest('id')
                        ->limit(50)
                        ->get()
                        ->pluck('label', 'id')
                        ->all())
                    ->attribute('collection_run_id'),

                SelectFilter::make('source')
                    ->relationship('source', 'name'),

                SelectFilter::make('adapter')
                    ->label('Adapter')
                    ->options(fn (): array => collect(SourceAdapter::cases())
                        ->mapWithKeys(fn (SourceAdapter $adapter): array => [$adapter->value => $adapter->getLabel()])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $value): Builder => $query->whereHas(
                            'source',
                            fn (Builder $sourceQuery) => $sourceQuery->where('adapter', $value)
                        ),
                    )),

                Filter::make('todays_runs')
                    ->label("Today's runs only")
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn(
                        'collection_run_id',
                        CollectionRun::query()->startedToday()->pluck('id'),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('openPosting')
                    ->label('Open posting')
                    ->url(fn (JobPosting $record): string => $record->url, shouldOpenInNewTab: true),
            ])
            ->socket(channel: 'job_postings', event: 'JobPostingsUpdated');
    }
}
