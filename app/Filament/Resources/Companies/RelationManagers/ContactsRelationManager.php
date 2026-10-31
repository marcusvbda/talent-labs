<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Enums\ContactTier;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\Contact;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('jobPosting'))
            ->columns([
                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('local_part')
                    ->label('Local part'),

                TextColumn::make('source')
                    ->badge(),

                TextColumn::make('tier')
                    ->state(fn (Contact $record): ?ContactTier => $record->tier())
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('jobPosting.title')
                    ->label('Posting')
                    ->limit(40)
                    ->placeholder('—')
                    ->url(fn (Contact $record): ?string => $record->jobPosting
                        ? JobPostingResource::getUrl('view', ['record' => $record->jobPosting])
                        : null),

                TextColumn::make('person_name')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('person_title')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('confidence')
                    ->badge(),

                TextColumn::make('checked_at')
                    ->dateTime(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->socket(channel: 'companies', event: 'CompanyUpdated');
    }
}
