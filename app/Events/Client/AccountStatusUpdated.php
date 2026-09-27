<?php

namespace App\Events\Client;

use App\Client\AccountStatusPresenter;
use App\Http\Resources\Client\AccountStatusResource;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class AccountStatusUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $userId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'account.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $user = User::findOrFail($this->userId);

        return ['status' => (new AccountStatusResource(app(AccountStatusPresenter::class)->forUser($user)))->resolve()];
    }
}
