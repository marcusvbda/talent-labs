<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\AdminGuard;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (): bool => filled((new AdminGuard)->violation(auth()->user(), $this->getUserRecord(), 'delete')))
                ->tooltip(fn (): ?string => (new AdminGuard)->violation(auth()->user(), $this->getUserRecord(), 'delete'))
                ->before(function (DeleteAction $action): void {
                    $reason = (new AdminGuard)->violation(auth()->user(), $this->getUserRecord(), 'delete');

                    if (filled($reason)) {
                        Notification::make()
                            ->title($reason)
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    protected function getUserRecord(): User
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $guard = new AdminGuard;
        $actor = auth()->user();

        $isDemoting = $record->is_admin && array_key_exists('is_admin', $data) && ! $data['is_admin'];
        $isBlocking = $record->status === UserStatus::Active
            && array_key_exists('status', $data)
            && $data['status'] === UserStatus::Blocked->value;

        if ($isDemoting) {
            $reason = $guard->violation($actor, $record, 'demote');

            if (filled($reason)) {
                Notification::make()
                    ->title($reason)
                    ->danger()
                    ->send();

                throw new Halt;
            }
        }

        if ($isBlocking) {
            $reason = $guard->violation($actor, $record, 'block');

            if (filled($reason)) {
                Notification::make()
                    ->title($reason)
                    ->danger()
                    ->send();

                throw new Halt;
            }
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
