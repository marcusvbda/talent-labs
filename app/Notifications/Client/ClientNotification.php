<?php

namespace App\Notifications\Client;

use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Events\Client\NotificationCreated;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * In-app notification for the client UI. The stored `data.type` is the contract's
 * `NotificationType` slug, and `send()` pushes the new row live.
 */
abstract class ClientNotification extends Notification
{
    use DispatchesClientEvent;

    /**
     * One of the contract's `NotificationType` slugs.
     */
    abstract public function notificationType(): string;

    /**
     * Client-safe values only: no URLs, no email addresses, no raw exception text.
     *
     * @return array<string, string|int|null>
     */
    abstract public function payload(): array;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toDatabase(object $notifiable): array
    {
        return ['type' => $this->notificationType(), ...$this->payload()];
    }

    public static function send(User $user, ClientNotification $notification): void
    {
        // Pre-assigning the id makes the stored row addressable: the sender clones the
        // notification per channel and keeps an id that is already set.
        if (! $notification->id) {
            $notification->id = (string) Str::uuid();
        }

        $user->notify($notification);

        $row = DatabaseNotification::query()->find($notification->id);

        if ($row !== null) {
            self::dispatchClientEvent(new NotificationCreated($row));
        }
    }
}
