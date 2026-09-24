<?php

namespace App\Integrations\Gmail;

use App\Data\OAuthTokenData;
use App\Integrations\Google\GoogleOAuthPlugin;
use LogicException;

class GmailPlugin extends GoogleOAuthPlugin
{
    public function key(): string
    {
        return 'gmail';
    }

    public function label(): string
    {
        return 'Gmail';
    }

    public function description(): string
    {
        return 'Send applications from your own Gmail account.';
    }

    public function category(): string
    {
        return 'Email';
    }

    public function icon(): string
    {
        return 'heroicon-o-envelope';
    }

    public function capabilities(): array
    {
        return ['email.send'];
    }

    public function validateConnection(OAuthTokenData $token): void
    {
        $this->ensureScopes($token, ['https://www.googleapis.com/auth/gmail.send']);

        if (blank($token->externalAccountId) || filter_var($token->accountEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('The connected Gmail identity is incomplete.');
        }
    }

    protected function scopes(): array
    {
        /** @var list<string> $scopes */
        $scopes = config('connected-integrations.gmail.scopes', []);

        return $scopes;
    }
}
