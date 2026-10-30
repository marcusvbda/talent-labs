<?php

namespace App\Services;

use App\Enums\ConnectedIntegrationStatus;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Exceptions\ConnectedIntegrationReauthorizationRequired;
use App\Exceptions\OAuthRefreshTokenRejected;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Notifications\Client\EmailReauthorizationRequired;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

class ConnectedIntegrationTokenManager
{
    use DispatchesClientEvent;

    public function __construct(private ConnectedIntegrationRegistry $registry) {}

    public function accessToken(User $user, string $pluginKey): string
    {
        $integration = $this->integration($user, $pluginKey);

        if ($integration->status !== ConnectedIntegrationStatus::Connected) {
            throw new ConnectedIntegrationReauthorizationRequired('The connected integration must be authorized again.');
        }

        if ($this->hasFreshAccessToken($integration)) {
            return (string) $integration->access_token;
        }

        return Cache::lock("connected-integration-refresh:{$integration->getKey()}", 30)
            ->block(5, function () use ($integration): string {
                $integration->refresh();

                if ($integration->status !== ConnectedIntegrationStatus::Connected) {
                    throw new ConnectedIntegrationReauthorizationRequired('The connected integration must be authorized again.');
                }

                if ($this->hasFreshAccessToken($integration)) {
                    return (string) $integration->access_token;
                }

                if (blank($integration->refresh_token)) {
                    $this->markReauthorizationRequired($integration);
                }

                $plugin = $this->registry->oauth($integration->plugin_key);

                try {
                    $token = $plugin->refreshAccessToken((string) $integration->refresh_token, $plugin->redirectUri());
                } catch (OAuthRefreshTokenRejected $exception) {
                    $this->markReauthorizationRequired($integration, $exception);
                } catch (Throwable $exception) {
                    $integration->forceFill(['last_error_at' => now()])->save();

                    throw $exception;
                }

                $integration->forceFill([
                    'status' => ConnectedIntegrationStatus::Connected,
                    'access_token' => $token->accessToken,
                    'refresh_token' => $token->refreshToken ?? $integration->refresh_token,
                    'granted_scopes' => $token->scopes === [] ? $integration->granted_scopes : $token->scopes,
                    'expires_at' => $token->expiresAt === null ? null : now()->setTimestamp($token->expiresAt),
                    'last_refreshed_at' => now(),
                    'last_error_at' => null,
                ])->save();

                return $token->accessToken;
            });
    }

    public function requireReauthorization(User $user, string $pluginKey, ?Throwable $previous = null): never
    {
        $this->markReauthorizationRequired($this->integration($user, $pluginKey), $previous);
    }

    private function integration(User $user, string $pluginKey): ConnectedIntegration
    {
        return ConnectedIntegration::query()
            ->whereBelongsTo($user)
            ->where('plugin_key', $pluginKey)
            ->firstOrFail();
    }

    private function hasFreshAccessToken(ConnectedIntegration $integration): bool
    {
        return filled($integration->access_token)
            && $integration->expires_at !== null
            && $integration->expires_at->isAfter(now()->addMinute());
    }

    private function markReauthorizationRequired(ConnectedIntegration $integration, ?Throwable $previous = null): never
    {
        $transitioned = ConnectedIntegration::query()
            ->whereKey($integration->getKey())
            ->where('status', ConnectedIntegrationStatus::Connected)
            ->update([
                'status' => ConnectedIntegrationStatus::ReauthorizationRequired,
                'access_token' => null,
                'refresh_token' => null,
                'credentials' => null,
                'expires_at' => null,
                'last_error_at' => now(),
            ]) > 0;

        if (! $transitioned) {
            ConnectedIntegration::query()
                ->whereKey($integration->getKey())
                ->update(['last_error_at' => now()]);
        }

        $integration->refresh();

        if ($transitioned) {
            // The query-builder update above skips model events, so dispatch explicitly.
            self::dispatchAccountStatusUpdated($integration->user_id);

            EmailReauthorizationRequired::send(
                $integration->user,
                new EmailReauthorizationRequired($this->providerLabel($integration->plugin_key)),
            );
        }

        throw new ConnectedIntegrationReauthorizationRequired(
            'The connected integration must be authorized again.',
            previous: $previous,
        );
    }

    /**
     * Label of the integration's plugin, or a neutral fallback when the key is not registered.
     */
    private function providerLabel(string $pluginKey): string
    {
        try {
            return $this->registry->get($pluginKey)->label();
        } catch (InvalidArgumentException) {
            return 'Email account';
        }
    }
}
