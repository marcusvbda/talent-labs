<?php

namespace App\Filament\Resources\Invitations\Pages;

use App\Enums\PlanKey;
use App\Filament\Resources\Invitations\InvitationResource;
use App\Invitations\InvitationTokens;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New invitation')
                ->schema([
                    TextInput::make('note')
                        ->label('Note')
                        ->maxLength(200)
                        ->placeholder('e.g. Wife'),

                    TextInput::make('email')
                        ->email()
                        ->nullable()
                        ->helperText('When set, the person must register with this exact address.'),

                    Select::make('plan_key')
                        ->options(PlanKey::class)
                        ->nullable()
                        ->placeholder('Use the default plan'),

                    TextInput::make('expiry_days')
                        ->label('Expires in (days)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(fn () => config('talent.invitations.default_expiry_days'))
                        ->required(),
                ])
                ->action(function (array $data, HasActions $livewire): void {
                    $raw = InvitationTokens::generate();

                    Invitation::create([
                        'token_hash' => InvitationTokens::hash($raw),
                        'note' => $data['note'] ?? null,
                        'email' => $data['email'] ?? null,
                        'plan_key' => $data['plan_key'] ?? null,
                        'created_by' => auth()->id(),
                        'expires_at' => now()->addDays((int) $data['expiry_days']),
                    ]);

                    $livewire->mountAction('showInvitationLink', arguments: [
                        'link' => InvitationTokens::link($raw),
                    ]);

                    Notification::make()
                        ->title('Invitation created.')
                        ->success()
                        ->send();
                })
                ->registerModalActions([
                    Action::make('showInvitationLink')
                        ->modalHeading('Sign-up link')
                        ->modalWidth(Width::Large)
                        ->closeModalByClickingAway(false)
                        ->closeModalByEscaping(false)
                        ->schema(fn (array $arguments): array => [
                            TextInput::make('link')
                                ->label('Sign-up link')
                                ->default($arguments['link'])
                                ->readOnly()
                                ->copyable()
                                ->helperText('This link is shown only once'),
                        ])
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                ]),
        ];
    }
}
