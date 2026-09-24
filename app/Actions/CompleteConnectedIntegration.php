<?php

namespace App\Actions;

use App\Data\OAuthTokenData;
use App\Enums\ConnectedIntegrationStatus;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompleteConnectedIntegration
{
    public function __construct(private ConnectedIntegrationRegistry $registry) {}

    public function run(User $user, string $pluginKey, OAuthTokenData $token): ConnectedIntegration
    {
        $plugin = $this->registry->get($pluginKey);
        $plugin->validateConnection($token);

        $integration = DB::transaction(function () use ($user, $pluginKey, $token): ConnectedIntegration {
            $integration = ConnectedIntegration::query()->firstOrNew([
                'user_id' => $user->getKey(),
                'plugin_key' => $pluginKey,
            ]);

            $sameExternalAccount = $integration->exists
                && filled($integration->external_account_id)
                && ($token->externalAccountId === null || $token->externalAccountId === $integration->external_account_id);
            $refreshToken = $token->refreshToken ?? ($sameExternalAccount ? $integration->refresh_token : null);

            if (blank($refreshToken)) {
                throw new RuntimeException('The provider did not return an offline refresh token.');
            }

            $integration->fill([
                'status' => ConnectedIntegrationStatus::Connected,
                'external_account_id' => $token->externalAccountId ?? $integration->external_account_id,
                'account_email' => $token->accountEmail ?? $integration->account_email,
                'account_name' => $token->accountName ?? $integration->account_name,
                'access_token' => $token->accessToken,
                'refresh_token' => $refreshToken,
                'granted_scopes' => $token->scopes,
                'metadata' => $token->metadata,
                'expires_at' => $token->expiresAt === null ? null : now()->setTimestamp($token->expiresAt),
                'connected_at' => now(),
                'last_refreshed_at' => null,
                'last_error_at' => null,
            ])->save();

            return $integration;
        });

        $plugin->afterConnected($integration);

        return $integration;
    }
}
