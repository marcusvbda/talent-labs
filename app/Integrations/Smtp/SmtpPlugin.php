<?php

namespace App\Integrations\Smtp;

use App\Contracts\CredentialIntegrationPlugin;
use App\Models\ConnectedIntegration;

class SmtpPlugin implements CredentialIntegrationPlugin
{
    public function key(): string
    {
        return 'smtp';
    }

    public function label(): string
    {
        return 'Other email (SMTP)';
    }

    public function description(): string
    {
        return "Send applications through your email provider's SMTP server.";
    }

    public function category(): string
    {
        return 'Email';
    }

    public function icon(): string
    {
        return 'heroicon-o-at-symbol';
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return ['email.send'];
    }

    public function disconnect(ConnectedIntegration $integration): void
    {
        // Nothing to revoke at the provider.
    }
}
