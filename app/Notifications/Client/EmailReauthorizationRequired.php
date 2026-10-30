<?php

namespace App\Notifications\Client;

class EmailReauthorizationRequired extends ClientNotification
{
    /**
     * @param  string  $provider  Label of the sending provider whose authorization expired.
     */
    public function __construct(private readonly string $provider) {}

    public function notificationType(): string
    {
        return 'email_reauthorization_required';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return ['provider' => $this->provider];
    }
}
