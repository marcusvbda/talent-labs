<?php

namespace App\Filament\App\Pages;

use App\Models\Application;
use App\Models\User;
use App\Outreach\Support\ClientSafeText;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class Applications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'applications';

    protected static ?string $title = 'Applications';

    protected static ?string $navigationLabel = 'Applications';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected string $view = 'filament.app.pages.applications';

    public function getHeading(): string
    {
        return 'Applications';
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                Application::query()
                    ->whereBelongsTo($user)
                    ->with(['company', 'jobPosting'])
            )
            ->defaultSort(fn ($query) => $query->orderByDesc('queued_at')->orderByDesc('id'))
            ->paginationPageOptions([25])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('company.name')
                    ->label('Company'),

                TextColumn::make('job')
                    ->label('Job')
                    ->state(fn (Application $record): string => $record->jobPosting->title ?? '—')
                    ->wrap(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('origin')
                    ->badge(),

                TextColumn::make('queued_at')
                    ->label('Queued')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->authorize(fn (Application $record): bool => Gate::allows('view', $record))
                    ->mutateRecordDataUsing(fn (array $data): array => Arr::only($data, ['id', 'company_id', 'status', 'origin', 'queued_at', 'sent_at']))
                    ->schema([
                        TextEntry::make('company.name')
                            ->label('Company'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('origin')
                            ->badge(),

                        TextEntry::make('queued_at')
                            ->label('Queued')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('sent_at')
                            ->label('Sent')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('subject')
                            ->state(fn (Application $record): string => ClientSafeText::redact($record->subject))
                            ->columnSpanFull(),

                        TextEntry::make('body')
                            ->state(fn (Application $record): string => ClientSafeText::redact($record->body))
                            ->columnSpanFull()
                            ->html(false)
                            ->extraEntryWrapperAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ])
            ->emptyStateHeading('No applications yet.')
            ->socket(channel: 'applications', event: 'ApplicationsUpdated');
    }
}
