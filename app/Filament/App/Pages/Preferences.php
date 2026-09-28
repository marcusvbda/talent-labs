<?php

namespace App\Filament\App\Pages;

use App\Actions\DisconnectConnectedIntegration;
use App\Enums\ConnectedIntegrationStatus;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Plans\PlanCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Preferences extends Page
{
    protected static ?string $slug = 'preferences';

    protected static ?string $title = 'Preferences';

    protected static ?string $navigationLabel = 'Preferences';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    private function gmailIntegration(): ?ConnectedIntegration
    {
        return ConnectedIntegration::query()
            ->where('user_id', auth()->id())
            ->where('plugin_key', 'gmail')
            ->first();
    }

    private function dailyLimit(): int
    {
        /** @var User $user */
        $user = auth()->user();

        return app(PlanCatalog::class)->for($user)->dailyLimit;
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

    public function content(Schema $schema): Schema
    {
        return $schema->components([
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
                    Text::make(fn (): string => 'Emails are sent from your own Gmail account, up to '.$this->dailyLimit().' per day.')->color('gray'),
                    Text::make('Applications leave from your address, so spam reports affect your own Gmail account.')->color('gray'),
                ]),
        ]);
    }
}
