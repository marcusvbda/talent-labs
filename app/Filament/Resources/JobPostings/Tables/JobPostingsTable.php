<?php

namespace App\Filament\Resources\JobPostings\Tables;

use App\Enums\ContactStatus;
use App\Enums\RoleFamily;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['collectionRun', 'source', 'company']))
            ->defaultGroup(
                Group::make('collection_run_id')
                    ->label('Run')
                    ->getTitleFromRecordUsing(fn (JobPosting $record): string => $record->collectionRun->label)
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('collection_run_id', 'desc'))
            )
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->extraHeaderAttributes(['style' => 'min-width: 28rem'])
                    ->searchable()
                    ->wrap(),

                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable(),

                IconColumn::make('is_remote')
                    ->label('Remote')
                    ->boolean(),

                TextColumn::make('contact_status')
                    ->label('Contact')
                    ->state(fn (JobPosting $record): ContactStatus => $record->company_id === null ? ContactStatus::Pending : $record->company->contact_status)
                    ->badge(),

                TextColumn::make('role_family')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?RoleFamily $state): string => $state === null ? 'gray' : 'primary')
                    ->placeholder('Other')
                    ->toggleable(),

                TextColumn::make('location')
                    ->placeholder('—'),

                TextColumn::make('source.adapter')
                    ->label('Adapter')
                    ->badge(),

                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
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

                SelectFilter::make('role_family')
                    ->label('Role family')
                    ->options(fn (): array => collect(RoleFamily::cases())
                        ->mapWithKeys(fn (RoleFamily $role): array => [$role->value => $role->getLabel()])
                        ->put('other', 'Other')
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $value === 'other'
                            ? $query->whereNull('role_family')
                            : $query->where('role_family', $value);
                    }),

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
