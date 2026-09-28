<?php

namespace App\Notifications\Client;

class DailyLimitReached extends ClientNotification
{
    public function __construct(public int $count) {}

    public function notificationType(): string
    {
        return 'daily_limit_reached';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return ['count' => $this->count];
    }
}
