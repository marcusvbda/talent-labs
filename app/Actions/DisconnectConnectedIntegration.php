<?php

namespace App\Actions;

use App\Enums\ConnectedIntegrationStatus;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

class DisconnectConnectedIntegration
{
    public function __construct(private ConnectedIntegrationRegistry $registry) {}

    public function run(User $user, string $pluginKey): void
    {
        $integration = ConnectedIntegration::query()
            ->whereBelongsTo($user)
            ->where('plugin_key', $pluginKey)
            ->firstOrFail();

        $disconnectFailed = false;

        try {
            $this->registry->get($pluginKey)->disconnect($integration);
        } catch (Throwable $exception) {
            $disconnectFailed = true;
            Log::warning('Connected integration provider cleanup failed; local credentials were cleared.', [
                'user_id' => $user->getKey(),
                'plugin_key' => $pluginKey,
                'exception_class' => $exception::class,
            ]);
        }

        $integration->forceFill([
            'status' => ConnectedIntegrationStatus::Disconnected,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'last_error_at' => $disconnectFailed ? now() : null,
        ])->save();
    }
}
