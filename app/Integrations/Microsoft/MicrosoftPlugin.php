<?php

namespace App\Integrations\Microsoft;

use App\Contracts\OAuthIntegrationPlugin;
use App\Data\OAuthTokenData;
use App\Exceptions\OAuthRefreshTokenRejected;
use App\Models\ConnectedIntegration;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use LogicException;

class MicrosoftPlugin implements OAuthIntegrationPlugin
{
    private const string GRAPH_SCOPE_PREFIX = 'https://graph.microsoft.com/';

    public function key(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Outlook / Microsoft 365';
    }

    public function description(): string
    {
        return 'Send applications from your Outlook or Microsoft 365 account.';
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

    /** Optional provider: only offered when both OAuth credentials are configured. */
    public function isAvailable(): bool
    {
        return filled(config('services.microsoft.client_id')) && filled(config('services.microsoft.client_secret'));
    }

    public function redirectUri(): string
    {
        $configured = config('services.microsoft.redirect');

        return is_string($configured) && filled($configured) ? $configured : url('/integrations/oauth/callback');
    }

    public function authorizationUrl(string $state, string $codeVerifier, string $redirectUri): string
    {
        return $this->provider($redirectUri)->getAuthorizationUrl([
            'state' => $state,
            'scope' => $this->scopes(),
            'prompt' => 'select_account',
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): OAuthTokenData
    {
        $provider = $this->provider($redirectUri);
        $provider->setPkceCode($codeVerifier);
        $token = $provider->getAccessToken('authorization_code', ['code' => $code]);

        if (! $token instanceof AccessToken) {
            throw new LogicException('Microsoft returned an unsupported access token implementation.');
        }

        $owner = $provider->getResourceOwner($token)->toArray();
        $email = $owner['mail'] ?? null;

        if (! is_string($email) || blank($email)) {
            $email = $owner['userPrincipalName'] ?? null;
        }

        $id = $owner['id'] ?? null;
        $name = $owner['displayName'] ?? null;

        return $this->tokenData(
            $token,
            is_string($id) ? $id : null,
            is_string($email) ? $email : null,
            is_string($name) ? $name : null,
        );
    }

    public function refreshAccessToken(string $refreshToken, string $redirectUri): OAuthTokenData
    {
        try {
            return $this->tokenData($this->provider($redirectUri)->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]));
        } catch (IdentityProviderException $exception) {
            $response = $exception->getResponseBody();

            if (is_array($response) && in_array($response['error'] ?? null, ['invalid_grant', 'interaction_required'], true)) {
                throw new OAuthRefreshTokenRejected('The provider rejected the refresh token.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function validateConnection(OAuthTokenData $token): void
    {
        $granted = array_map(
            fn (string $scope): string => strtolower(str_replace(self::GRAPH_SCOPE_PREFIX, '', $scope)),
            $token->scopes,
        );

        if (! in_array('mail.send', $granted, true)) {
            throw new LogicException('A required Microsoft OAuth scope was not granted.');
        }

        if (blank($token->externalAccountId) || filter_var($token->accountEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('The connected Microsoft identity is incomplete.');
        }
    }

    public function afterConnected(ConnectedIntegration $integration): void {}

    public function disconnect(ConnectedIntegration $integration): void {}

    /** @return list<string> */
    private function scopes(): array
    {
        /** @var list<string> $scopes */
        $scopes = config('connected-integrations.microsoft.scopes', []);

        return $scopes;
    }

    private function provider(string $redirectUri): GenericProvider
    {
        $clientId = config('services.microsoft.client_id');
        $clientSecret = config('services.microsoft.client_secret');

        if (! is_string($clientId) || blank($clientId) || ! is_string($clientSecret) || blank($clientSecret)) {
            throw new LogicException('Microsoft OAuth credentials are not configured.');
        }

        return new GenericProvider([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
            'scopeSeparator' => ' ',
            'responseResourceOwnerId' => 'id',
        ], ['httpClient' => new Client(['connect_timeout' => 3, 'timeout' => 10])]);
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    private function tokenData(AccessTokenInterface $token, ?string $externalAccountId = null, ?string $accountEmail = null, ?string $accountName = null): OAuthTokenData
    {
        $scope = $token->getValues()['scope'] ?? null;

        return new OAuthTokenData(
            accessToken: (string) $token->getToken(),
            refreshToken: $token->getRefreshToken(),
            expiresAt: $token->getExpires(),
            scopes: is_string($scope) ? array_values(array_filter(explode(' ', $scope))) : [],
            externalAccountId: $externalAccountId,
            accountEmail: $accountEmail,
            accountName: $accountName,
        );
    }
}
