<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\Application;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),

                TextEntry::make('company.name')
                    ->label('Company'),

                TextEntry::make('jobPosting.title')
                    ->label('Job')
                    ->placeholder('—')
                    ->url(fn (Application $record): ?string => $record->jobPosting
                        ? JobPostingResource::getUrl('view', ['record' => $record->jobPosting])
                        : null),

                TextEntry::make('recipient_email')
                    ->label('Recipient'),

                TextEntry::make('status')
                    ->badge(),

                TextEntry::make('origin')
                    ->badge(),

                TextEntry::make('attempts'),

                TextEntry::make('subject')
                    ->columnSpanFull(),

                TextEntry::make('body')
                    ->extraAttributes(['class' => 'whitespace-pre-line'])
                    ->columnSpanFull(),

                TextEntry::make('last_error')
                    ->label('Last error')
                    ->placeholder('—')
                    ->columnSpanFull(),

                TextEntry::make('provider_message_id')
                    ->label('Provider message ID')
                    ->placeholder('—'),

                TextEntry::make('queued_at')
                    ->label('Queued')
                    ->dateTime()
                    ->placeholder('—'),

                TextEntry::make('scheduled_for')
                    ->label('Scheduled for')
                    ->dateTime()
                    ->placeholder('—'),

                TextEntry::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->placeholder('—'),

                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
