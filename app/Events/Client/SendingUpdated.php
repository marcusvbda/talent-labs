<?php

namespace App\Events\Client;

use App\Client\LiveSendingPresenter;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class SendingUpdated implements ShouldBroadcastNow
{
    use DispatchesClientEvent;

    public function __construct(public int $userId) {}

    /**
     * Best-effort broadcast of the user's live sending payload.
     */
    public static function broadcastFor(int $userId): void
    {
        self::dispatchClientEvent(new self($userId));
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'sending.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $user = User::findOrFail($this->userId);

        return ['sending' => app(LiveSendingPresenter::class)->forUser($user)];
    }
}
