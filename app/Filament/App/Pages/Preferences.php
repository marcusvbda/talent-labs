<?php

namespace App\Filament\App\Pages;

use App\Actions\DisconnectConnectedIntegration;
use App\Enums\ConnectedIntegrationStatus;
use App\Models\ConnectedIntegration;
use App\Models\JobPreference;
use App\Outreach\OutreachLimits;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Outreach\Support\ClientSafeText;
use App\Outreach\Support\StackNormalizer;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read Schema $form
 */
class Preferences extends Page
{
    protected static ?string $slug = 'preferences';

    protected static ?string $title = 'Preferences';

    protected static ?string $navigationLabel = 'Preferences';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $pref = $this->preference();

        $this->form->fill([
            'titles' => $pref->titles ?? [],
            'keywords' => $pref->keywords ?? [],
            'stack' => $pref->stack ?? [],
            'locations' => $pref->locations ?? [],
            'accepts_remote' => $pref->accepts_remote,
            'cv_path' => $this->ownsCvPath($pref->cv_path) ? $pref->cv_path : null,
            'email_subject' => $pref->email_subject,
            'email_body' => $pref->email_body,
        ]);
    }

    private function gmailIntegration(): ?ConnectedIntegration
    {
        return ConnectedIntegration::query()
            ->where('user_id', auth()->id())
            ->where('plugin_key', 'gmail')
            ->first();
    }

    private function gmailStatus(): ?ConnectedIntegrationStatus
    {
        return $this->gmailIntegration()?->status;
    }

    public function connectGmailAction(): Action
    {
        return Action::make('connectGmail')
            ->label('Connect Gmail')
            ->icon(Heroicon::OutlinedEnvelope)
            ->url(fn (): string => route('integrations.oauth.connect', 'gmail'))
            ->visible(fn (): bool => in_array($this->gmailStatus(), [null, ConnectedIntegrationStatus::Disconnected], true));
    }

    public function reconnectGmailAction(): Action
    {
        return Action::make('reconnectGmail')
            ->label('Reconnect')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->url(fn (): string => route('integrations.oauth.reconnect', 'gmail'))
            ->visible(fn (): bool => $this->gmailStatus() === ConnectedIntegrationStatus::ReauthorizationRequired);
    }

    public function disconnectGmailAction(): Action
    {
        return Action::make('disconnectGmail')
            ->label('Disconnect')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Disconnect Gmail')
            ->modalDescription('You won\'t be able to apply until you connect Gmail again.')
            ->visible(fn (): bool => $this->gmailStatus() === ConnectedIntegrationStatus::Connected)
            ->action(function (): void {
                $user = auth()->user();

                if ($this->gmailIntegration() === null) {
                    return;
                }

                app(DisconnectConnectedIntegration::class)->run($user, 'gmail');

                Notification::make()->title('Gmail disconnected')->success()->send();
            });
    }

    protected function preference(): JobPreference
    {
        return JobPreference::firstOrCreate(
            ['user_id' => auth()->id()],
            [
                'email_subject' => ApplicationTemplateRenderer::DEFAULT_SUBJECT,
                'email_body' => ApplicationTemplateRenderer::DEFAULT_BODY,
            ],
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Job preferences')
                    ->schema([
                        TagsInput::make('titles')->label('Job titles'),
                        TagsInput::make('keywords')->label('Description keywords'),
                        TagsInput::make('stack')->label('Stack'),
                        TagsInput::make('locations')->label('Locations'),
                        Toggle::make('accepts_remote')->label('Accept remote jobs'),
                    ]),
                Section::make('Gmail')
                    ->schema([
                        Text::make(fn (): string => match ($this->gmailStatus()) {
                            ConnectedIntegrationStatus::Connected => 'Connected as '.$this->gmailIntegration()?->account_email,
                            ConnectedIntegrationStatus::ReauthorizationRequired => 'Your Gmail connection expired. Reconnect to keep sending applications.',
                            default => 'Gmail is not connected.',
                        })->color(fn (): ?string => $this->gmailStatus() === ConnectedIntegrationStatus::ReauthorizationRequired ? 'warning' : null),
                        Actions::make([
                            $this->connectGmailAction(),
                            $this->reconnectGmailAction(),
                            $this->disconnectGmailAction(),
                        ]),
                        Text::make('Emails are sent from your own Gmail account, up to '.OutreachLimits::DAILY_SEND_LIMIT.' per day.')->color('gray'),
                        Text::make('Applications leave from your address, so spam reports affect your own Gmail account.')->color('gray'),
                    ]),
                Section::make('CV')
                    ->schema([
                        FileUpload::make('cv_path')
                            ->label('CV (PDF)')
                            ->disk('local')
                            ->directory(fn (): string => 'cvs/'.auth()->id())
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(5120)
                            ->storeFileNamesIn('cv_original_name')
                            ->openable(false)
                            ->downloadable(false),
                        Actions::make([
                            Action::make('downloadCv')
                                ->label('Download CV')
                                ->icon(Heroicon::OutlinedArrowDownTray)
                                ->color('gray')
                                ->visible(fn (): bool => $this->ownsCvPath(
                                    JobPreference::query()->where('user_id', auth()->id())->value('cv_path')
                                ))
                                ->action(fn (): StreamedResponse => $this->downloadCv()),
                        ]),
                    ]),
                Section::make('Email template')
                    ->schema([
                        TextInput::make('email_subject')
                            ->label('Subject')
                            ->required()
                            ->maxLength(200)
                            ->rule(fn (): Closure => $this->templateRule())
                            ->helperText($this->variablesHelp()),
                        Textarea::make('email_body')
                            ->label('Body')
                            ->required()
                            ->maxLength(5000)
                            ->rows(10)
                            ->rule(fn (): Closure => $this->templateRule())
                            ->helperText($this->variablesHelp()),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('preferences-form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Save')
                            ->submit('preferences-form'),
                        $this->previewAction(),
                    ]),
                ]),
        ]);
    }

    private function variablesHelp(): string
    {
        return 'Available variables: '.$this->allowedList();
    }

    private function allowedList(): string
    {
        return implode(', ', array_map(
            fn (string $name): string => '{{ '.$name.' }}',
            ApplicationTemplateRenderer::ALLOWED_VARIABLES,
        ));
    }

    private function templateRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $unknown = ApplicationTemplateRenderer::unknownVariables((string) $value);

            if ($unknown === []) {
                return;
            }

            $list = implode(', ', array_map(fn (string $name): string => '{{ '.$name.' }}', $unknown));

            $fail('Unknown variables: '.$list.'. Allowed: '.$this->allowedList().'.');
        };
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->color('gray')
            ->icon(Heroicon::OutlinedEye)
            ->modalHeading('Email preview')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): HtmlString {
                /** @var array<string, mixed> $state */
                $state = $this->form->getRawState();
                $user = auth()->user();

                $posting = MatchingJobPostings::forUser($user)
                    ->orderByDesc('job_postings.first_seen_at')
                    ->orderByDesc('job_postings.id')
                    ->first();

                $variables = $posting !== null
                    ? ApplicationTemplateRenderer::variablesFor($user, $posting)
                    : [
                        'company' => 'Acme',
                        'job_title' => 'Backend Engineer',
                        'job_location' => 'Remote',
                        'job_url' => 'https://example.com/jobs/123',
                        'client_name' => $user->name,
                    ];

                $subject = ClientSafeText::redact(ApplicationTemplateRenderer::render((string) ($state['email_subject'] ?? ''), $variables));
                $body = ClientSafeText::redact(ApplicationTemplateRenderer::render((string) ($state['email_body'] ?? ''), $variables));

                return new HtmlString(
                    '<div class="space-y-4">'
                    .'<div class="font-semibold">'.e($subject).'</div>'
                    .'<div class="whitespace-pre-line text-sm">'.e($body).'</div>'
                    .'</div>'
                );
            });
    }

    public function save(): void
    {
        $pref = $this->preference();

        Gate::authorize('update', $pref);

        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        $oldPath = $pref->cv_path;
        $newPath = $state['cv_path'] ?? null;
        $newPath = is_array($newPath) ? (end($newPath) ?: null) : $newPath;

        if ($newPath !== null && ! $this->ownsCvPath($newPath)) {
            throw ValidationException::withMessages(['data.cv_path' => 'The uploaded CV is invalid.']);
        }

        if (! $this->ownsCvPath($oldPath)) {
            $oldPath = null;
        }

        $pref->fill([
            'titles' => $this->cleanList($state['titles'] ?? []),
            'keywords' => $this->cleanList($state['keywords'] ?? []),
            'stack' => StackNormalizer::normalize($state['stack'] ?? []),
            'locations' => $this->cleanList($state['locations'] ?? []),
            'accepts_remote' => (bool) ($state['accepts_remote'] ?? false),
            'cv_path' => $newPath,
            'cv_original_name' => $newPath === null ? null : ($state['cv_original_name'] ?? $pref->cv_original_name),
            'email_subject' => $state['email_subject'],
            'email_body' => $state['email_body'],
        ])->save();

        if (filled($oldPath) && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->data['stack'] = $pref->stack;

        Notification::make()->title('Preferences saved')->success()->send();
    }

    private function ownsCvPath(?string $path): bool
    {
        if (blank($path) || ! str_starts_with($path, 'cvs/'.auth()->id().'/')) {
            return false;
        }

        if (str_contains($path, '\\') || str_contains($path, "\0") || in_array('..', explode('/', $path), true)) {
            return false;
        }

        return Storage::disk('local')->exists($path);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function cleanList(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $v): string => trim((string) $v), $values),
            fn (string $v): bool => $v !== '',
        )));
    }

    public function downloadCv(): StreamedResponse
    {
        $pref = JobPreference::query()->where('user_id', auth()->id())->firstOrFail();

        Gate::authorize('view', $pref);

        abort_if(! $this->ownsCvPath($pref->cv_path), 404);

        return Storage::disk('local')->download($pref->cv_path, $pref->cv_original_name ?? basename($pref->cv_path));
    }
}
