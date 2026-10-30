<?php

namespace App\Notifications\Client;

class SendingProviderLimitReached extends ClientNotification
{
    public function notificationType(): string
    {
        return 'sending_provider_limit';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return [];
    }
}
