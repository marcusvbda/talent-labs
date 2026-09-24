<?php

namespace App\Filament\App\Pages;

use App\Enums\ApplicationOrigin;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\Data\SendEligibility;
use App\Outreach\OutreachLimits;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Outreach\Support\ClientSafeText;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Jobs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = '/';

    protected static ?string $title = 'Jobs';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected string $view = 'filament.app.pages.jobs';

    protected ?SendEligibility $eligibility = null;

    public function getHeading(): string
    {
        return 'Jobs';
    }

    protected function eligibility(): SendEligibility
    {
        /** @var User $user */
        $user = auth()->user();

        return $this->eligibility ??= app(CanSendApplications::class)->check($user);
    }

    private static function overLimitMessage(int $companies, int $remaining): string
    {
        return "You selected {$companies} companies but can send only {$remaining} more today (daily limit ".OutreachLimits::DAILY_SEND_LIMIT.'). Deselect some jobs to continue.';
    }

    /**
     * One posting per company (the first of each), keeping the selection order.
     *
     * @param  Collection<int, JobPosting>  $records
     * @return Collection<int, JobPosting>
     */
    private static function onePerCompany(Collection $records): Collection
    {
        return $records
            ->unique(fn (JobPosting $posting): string => $posting->company_id === null ? 'posting-'.$posting->id : 'company-'.$posting->company_id)
            ->values();
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->query(
                MatchingJobPostings::forUser($user)->with(['source', 'company', 'profile'])
            )
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw('job_postings.published_at DESC NULLS LAST')
                ->orderBy('job_postings.id', 'desc'))
            ->paginationPageOptions([25])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('title')
                    ->wrap(),

                TextColumn::make('company')
                    ->label('Company')
                    ->state(fn (JobPosting $record): string => $record->company->name ?? $record->company_name),

                TextColumn::make('location')
                    ->state(function (JobPosting $record): string {
                        $remote = $record->profile?->is_remote === true || $record->is_remote;
                        $parts = array_filter([$record->location, $remote ? 'Remote' : null], fn (?string $p): bool => $p !== null && $p !== '');

                        return $parts === [] ? '—' : implode(' · ', $parts);
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->mutateRecordDataUsing(fn (array $data): array => Arr::only($data, [
                        'id', 'title', 'company_name', 'location', 'is_remote', 'department', 'employment_type', 'published_at',
                    ]))
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

                        TextEntry::make('stack')
                            ->badge()
                            ->state(fn (JobPosting $record): array => $record->profile->stack)
                            ->placeholder('—'),

                        TextEntry::make('summary')
                            ->state(fn (JobPosting $record): ?string => $record->profile?->summary)
                            ->columnSpanFull()
                            ->placeholder('—'),

                        TextEntry::make('description_text')
                            ->label('Description')
                            ->state(fn (JobPosting $record): string => ClientSafeText::redact($record->description_text))
                            ->columnSpanFull()
                            ->prose(),
                    ]),
            ])
            ->maxSelectableRecords(fn (): int => max(1, $this->eligibility()->remaining))
            ->toolbarActions([$this->sendSelectedAction()])
            ->emptyStateHeading('No matching jobs yet.')
            ->emptyStateDescription(null)
            ->socket(channel: 'job_postings', event: 'JobPostingsUpdated');
    }

    private function sendSelectedAction(): BulkAction
    {
        return BulkAction::make('sendSelected')
            ->label('Send to selected')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->disabled(fn (): bool => ! $this->eligibility()->ok())
            ->tooltip(fn (): ?string => $this->eligibility()->ok() ? null : implode(' ', $this->eligibility()->unmet))
            ->modalHeading('Send applications')
            ->modalSubmitActionLabel(fn (Collection $records): string => 'Send '.self::onePerCompany($records)->count().' applications')
            ->modalSubmitAction(fn (Action $action, Collection $records): Action|false => self::onePerCompany($records)->count() > $this->eligibility()->remaining ? false : $action)
            ->modalContent(function (Collection $records) {
                /** @var User $user */
                $user = auth()->user();
                $postings = self::onePerCompany($records);
                $names = $postings->map(fn (JobPosting $p): string => $p->company->name ?? $p->company_name)->values();
                $first = $records->first();
                $subject = null;

                if ($first instanceof JobPosting) {
                    $subject = ClientSafeText::redact(ApplicationTemplateRenderer::render(
                        (string) $user->jobPreference?->email_subject,
                        ApplicationTemplateRenderer::variablesFor($user, $first),
                    ));
                }

                $remaining = $this->eligibility()->remaining;

                return view('filament.app.pages.partials.send-selected-modal', [
                    'jobs' => $records->count(),
                    'companies' => $postings->count(),
                    'names' => $names->take(10)->all(),
                    'more' => max(0, $names->count() - 10),
                    'attachment' => $user->jobPreference?->cv_original_name ?: 'CV',
                    'subject' => $subject,
                    'remaining' => $remaining,
                    'limit' => OutreachLimits::DAILY_SEND_LIMIT,
                    'overLimit' => $postings->count() > $remaining ? self::overLimitMessage($postings->count(), $remaining) : null,
                ]);
            })
            ->action(function (Collection $records): void {
                /** @var User $user */
                $user = auth()->user();

                $this->eligibility = null;
                $eligibility = $this->eligibility();

                if (! $eligibility->ok()) {
                    Notification::make()->title(implode(' ', $eligibility->unmet))->danger()->send();

                    return;
                }

                $postings = self::onePerCompany($records);

                if ($postings->count() > $eligibility->remaining) {
                    Notification::make()
                        ->title(self::overLimitMessage($postings->count(), $eligibility->remaining))
                        ->danger()
                        ->send();

                    return;
                }

                $queue = app(QueueApplication::class);
                $queued = 0;
                $skipped = [];

                foreach ($postings as $posting) {
                    if ($queue->handle($user, $posting, ApplicationOrigin::Manual) !== null) {
                        $queued++;

                        continue;
                    }

                    $skipped[] = ($posting->company->name ?? $posting->company_name).': '.($queue->rejectionReason() ?? 'Could not be queued.');
                }

                $this->eligibility = null;

                $skippedLine = $skipped === []
                    ? null
                    : 'Skipped: '.implode(' ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? ' and '.(count($skipped) - 5).' more.' : '');

                if ($queued === 0) {
                    Notification::make()
                        ->title('No application was queued')
                        ->body($skippedLine)
                        ->danger()
                        ->send();

                    return;
                }

                $notification = Notification::make()
                    ->title("{$queued} ".($queued === 1 ? 'application' : 'applications').' queued')
                    ->body('They are being sent now.'.($skippedLine === null ? '' : ' '.$skippedLine));

                ($skippedLine === null ? $notification->success() : $notification->warning())->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
