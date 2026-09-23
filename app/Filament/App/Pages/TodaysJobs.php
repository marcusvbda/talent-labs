<?php

namespace App\Filament\App\Pages;

use App\Models\JobPosting;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TodaysJobs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = '/';

    protected static ?string $title = "Today's jobs";

    protected string $view = 'filament.app.pages.todays-jobs';

    public function getHeading(): string
    {
        return "Today's jobs";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                JobPosting::query()
                    ->whereHas('collectionRun', fn (Builder $query) => $query->startedToday())
                    ->with(['source', 'collectionRun'])
            )
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw('published_at DESC NULLS LAST')
                ->orderBy('id', 'desc'))
            ->paginationPageOptions([25])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('title')
                    ->wrap(),

                TextColumn::make('company_name')
                    ->label('Company'),

                TextColumn::make('location')
                    ->placeholder('—'),

                IconColumn::make('is_remote')
                    ->label('Remote')
                    ->boolean(),

                TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (JobPosting $record): string => $record->source->adapter->sourceLabel()),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->since()
                    ->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        TextEntry::make('title'),

                        TextEntry::make('company_name')
                            ->label('Company'),

                        TextEntry::make('location')
                            ->placeholder('—'),

                        IconEntry::make('is_remote')
                            ->label('Remote')
                            ->boolean(),

                        TextEntry::make('department')
                            ->placeholder('—'),

                        TextEntry::make('employment_type')
                            ->placeholder('—'),

                        TextEntry::make('published_at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('source')
                            ->label('Source')
                            ->state(fn (JobPosting $record): string => $record->source->adapter->sourceLabel()),

                        TextEntry::make('description_text')
                            ->label('Description')
                            ->columnSpanFull()
                            ->prose(),
                    ]),
                Action::make('openPosting')
                    ->label('Open posting')
                    ->url(fn (JobPosting $record): string => $record->url, shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('No jobs collected today yet.')
            ->emptyStateDescription(null)
            ->socket(channel: 'job_postings', event: 'JobPostingsUpdated');
    }
}
