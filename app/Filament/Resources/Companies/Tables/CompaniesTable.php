<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Contacts\Actions\RetryCompanyDiscovery;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Enums\OutreachStatus;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, HasTable $livewire): Builder => $query->when(
                ! ($livewire->getTableFilterState('show_not_verifiable')['isActive'] ?? false),
                fn (Builder $query): Builder => $query->where('outreach_status', '!=', OutreachStatus::NotVerifiable),
            ))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('domain')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('domain_status')
                    ->badge(),

                TextColumn::make('contact_status')
                    ->badge(),

                TextColumn::make('outreach_status')
                    ->label('Outreach')
                    ->badge(),

                IconColumn::make('is_catch_all')
                    ->label('Catch-all')
                    ->icon(fn (?bool $state): Heroicon => match ($state) {
                        true => Heroicon::OutlinedExclamationTriangle,
                        false => Heroicon::OutlinedXCircle,
                        null => Heroicon::OutlinedMinus,
                    })
                    ->color(fn (?bool $state): string => match ($state) {
                        true => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->sortable(),

                TextColumn::make('domain_checked_at')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('contact_checked_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('domain_status')
                    ->options(DomainStatus::class),

                SelectFilter::make('contact_status')
                    ->options(ContactStatus::class),

                Filter::make('show_not_verifiable')
                    ->label('Show not-verifiable')
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retryDiscovery')
                    ->label('Retry discovery')
                    ->requiresConfirmation()
                    ->modalDescription("Resets this company's domain and contact results and runs discovery again. Existing contacts are removed.")
                    ->action(function (Company $record): void {
                        app(RetryCompanyDiscovery::class)->handle($record);

                        Notification::make()
                            ->title("Discovery queued for {$record->name}")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw('contact_checked_at DESC NULLS FIRST'))
            ->socket(channel: 'companies', event: 'CompanyUpdated');
    }
}
