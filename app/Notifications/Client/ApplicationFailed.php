<?php

namespace App\Notifications\Client;

class ApplicationFailed extends ClientNotification
{
    public function __construct(public string $companyName) {}

    public function notificationType(): string
    {
        return 'application_failed';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function payload(): array
    {
        return ['company' => $this->companyName];
    }
}
