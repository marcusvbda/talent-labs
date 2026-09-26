<?php

namespace App\Filament\Resources\JobPostings\Schemas;

use App\Enums\RoleFamily;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class JobPostingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),

                TextEntry::make('company_name')
                    ->label('Company'),

                TextEntry::make('location')
                    ->placeholder('—'),

                IconEntry::make('is_remote')
                    ->label('Remote')
                    ->boolean(),

                TextEntry::make('role_family')
                    ->label('Role family')
                    ->badge()
                    ->color(fn (?RoleFamily $state): string => $state === null ? 'gray' : 'primary')
                    ->placeholder('Other'),

                TextEntry::make('department')
                    ->placeholder('—'),

                TextEntry::make('employment_type')
                    ->placeholder('—'),

                TextEntry::make('url')
                    ->label('Posting URL')
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab(),

                TextEntry::make('apply_url')
                    ->label('Apply URL')
                    ->placeholder('—')
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab(),

                TextEntry::make('published_at')
                    ->dateTime()
                    ->placeholder('—'),

                TextEntry::make('first_seen_at')
                    ->dateTime(),

                TextEntry::make('last_seen_at')
                    ->dateTime(),

                TextEntry::make('source.name')
                    ->label('Source'),

                TextEntry::make('collectionRun.label')
                    ->label('Run'),

                TextEntry::make('description_text')
                    ->label('Description')
                    ->columnSpanFull()
                    ->prose(),
            ]);
    }
}
