<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),

                TextEntry::make('normalized_name')
                    ->label('Normalized name'),

                TextEntry::make('domain')
                    ->placeholder('—'),

                TextEntry::make('domain_status')
                    ->badge(),

                TextEntry::make('domain_checked_at')
                    ->dateTime()
                    ->placeholder('—'),

                TextEntry::make('contact_status')
                    ->badge(),

                TextEntry::make('contact_checked_at')
                    ->dateTime()
                    ->placeholder('—'),

                IconEntry::make('is_catch_all')
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

                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
