<?php

namespace App\Notifications\Client;

class PaymentFailed extends ClientNotification
{
    public function notificationType(): string
    {
        return 'payment_failed';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return [];
    }
}
