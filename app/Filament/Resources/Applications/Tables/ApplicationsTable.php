<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Enums\ApplicationOrigin;
use App\Enums\ApplicationStatus;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'company', 'jobPosting']))
            ->columns([
                TextColumn::make('user.name')
                    ->label('User'),

                TextColumn::make('company.name')
                    ->label('Company'),

                TextColumn::make('jobPosting.title')
                    ->label('Job')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('origin')
                    ->badge(),

                TextColumn::make('attempts'),

                TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),

                TextColumn::make('queued_at')
                    ->label('Queued')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options(ApplicationStatus::class),

                SelectFilter::make('origin')
                    ->options(ApplicationOrigin::class),

                Filter::make('date')
                    ->label('Queued date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->where('queued_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->where('queued_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Queued from '.Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Queued until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('queued_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->socket(channel: 'applications', event: 'ApplicationsUpdated');
    }
}
