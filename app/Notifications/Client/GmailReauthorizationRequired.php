<?php

namespace App\Notifications\Client;

class GmailReauthorizationRequired extends ClientNotification
{
    public function notificationType(): string
    {
        return 'gmail_reauthorization_required';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return ['provider' => 'Gmail'];
    }
}
