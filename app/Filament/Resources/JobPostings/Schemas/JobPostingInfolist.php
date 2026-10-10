<?php

namespace App\Filament\Resources\JobPostings\Schemas;

use App\Enums\RoleFamily;
use App\Enums\SourceContactKind;
use App\Models\JobPosting;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
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

                TextEntry::make('ineligible_reason')
                    ->label('Not eligible')
                    ->color('danger')
                    ->hidden(fn (?JobPosting $record): bool => blank($record?->ineligible_reason)),

                TextEntry::make('restrictions')
                    ->label('Restrictions')
                    ->state(fn (?JobPosting $record): array => array_map(
                        fn (array $restriction): string => $restriction['value'] === null ? $restriction['kind'] : "{$restriction['kind']}: {$restriction['value']}",
                        $record->restrictions ?? [],
                    ))
                    ->badge()
                    ->color('warning')
                    ->hidden(fn (?JobPosting $record): bool => blank($record?->restrictions)),

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

                Section::make('Source contacts')
                    ->description('Contacts exposed by the source. Not used as application recipients.')
                    ->columnSpanFull()
                    ->visible(fn (?JobPosting $record): bool => filled($record?->source_contacts))
                    ->schema([
                        RepeatableEntry::make('source_contacts')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('kind')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : (SourceContactKind::tryFrom($state)?->getLabel() ?? $state))
                                    ->placeholder('—'),
                                TextEntry::make('name')
                                    ->placeholder('—'),
                                TextEntry::make('title')
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->copyable()
                                    ->placeholder('—'),
                            ]),
                    ]),

                TextEntry::make('description_text')
                    ->label('Description')
                    ->columnSpanFull()
                    ->prose(),
            ]);
    }
}
