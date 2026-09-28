<?php

namespace App\Notifications\Client;

class SendingAutoPaused extends ClientNotification
{
    /**
     * @param  string  $reason  A `SendingPauseReason` value.
     */
    public function __construct(public string $reason) {}

    public function notificationType(): string
    {
        return 'sending_auto_paused';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return ['reason' => $this->reason];
    }
}
