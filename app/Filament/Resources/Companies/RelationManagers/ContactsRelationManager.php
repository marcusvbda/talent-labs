<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('local_part')
                    ->label('Alias'),

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
