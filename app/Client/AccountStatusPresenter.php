<?php

namespace App\Client;

use App\Contracts\CredentialIntegrationPlugin;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\SendingPauseReason;
use App\Models\ApplicationProfile;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Plans\PlanCatalog;
use App\Services\ConnectedIntegrationRegistry;

final class AccountStatusPresenter
{
    public function __construct(
        private PlanCatalog $plans,
        private ConnectedIntegrationRegistry $integrations,
    ) {}

    /**
     * Build the `AccountStatus` contract (resources/js/types/contracts.ts) for the user.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $plan = $this->plans->for($user);

        $account = $user->sendingIntegration;
        $accountState = match ($account?->status) {
            ConnectedIntegrationStatus::Connected => 'connected',
            ConnectedIntegrationStatus::ReauthorizationRequired => 'reauthorization_required',
            ConnectedIntegrationStatus::Disconnected, null => 'disconnected',
        };
        $accountEmail = $account !== null && $accountState === 'connected' ? $account->account_email : null;

        $preference = $user->jobPreference()->first();
        $languages = ApplicationProfile::activeCompleteLanguagesFor($user);

        $steps = [
            ['key' => 'basics', 'done' => $user->country !== null && $user->timezone !== null],
            ['key' => 'email', 'done' => $accountState === 'connected'],
            ['key' => 'profile', 'done' => $languages !== []],
            ['key' => 'preferences', 'done' => $preference?->saved_at !== null],
        ];

        return [
            'plan' => [
                'key' => $plan->key->value,
                'name' => $plan->name,
                'mode' => $plan->mode,
                'dailyLimit' => $plan->dailyLimit,
            ],
            'quota' => $this->quota($user),
            'sendingAccount' => [
                'state' => $accountState,
                'accountEmail' => $accountEmail,
                'providerKey' => $account?->plugin_key,
                // Key and kind only: label/description are localized client-side, because the
                // status is also broadcast, where the server locale is not the user's.
                'providers' => array_map(
                    fn (string $key): array => [
                        'key' => $key,
                        'kind' => $this->integrations->get($key) instanceof CredentialIntegrationPlugin ? 'credential' : 'oauth',
                    ],
                    // Unavailable plugins (e.g. unconfigured Microsoft) are not offered for new connections.
                    array_values(array_filter(
                        $this->integrations->sendingPluginKeys(),
                        fn (string $key): bool => $this->integrations->get($key)->isAvailable(),
                    )),
                ),
                'smtpDefaults' => $account?->plugin_key === 'smtp' ? $this->smtpDefaults($account) : null,
            ],
            'sending' => [
                'paused' => $user->isSendingPaused(),
                'autoPausedReason' => in_array($user->sending_pause_reason, [SendingPauseReason::ReauthorizationRequired, SendingPauseReason::RepeatedFailures, SendingPauseReason::ProviderLimit], true)
                    ? $user->sending_pause_reason->value
                    : null,
            ],
            'onboarding' => [
                'complete' => collect($steps)->every(fn (array $step): bool => $step['done']),
                'steps' => $steps,
            ],
            'profiles' => [
                'activeLanguages' => $languages,
            ],
            'region' => $user->region->value,
            'country' => $user->country,
            'timezone' => $user->timezone,
            'unreadNotifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * Prefill for the SMTP form. Never includes the username or password.
     *
     * @return array{preset: string, host: string, port: int, security: string, fromAddress: string, displayName: string}
     */
    private function smtpDefaults(ConnectedIntegration $account): array
    {
        $metadata = $account->metadata ?? [];

        return [
            'preset' => (string) ($metadata['preset'] ?? 'custom'),
            'host' => (string) ($metadata['host'] ?? ''),
            'port' => (int) ($metadata['port'] ?? 0),
            'security' => (string) ($metadata['security'] ?? 'tls'),
            'fromAddress' => (string) $account->account_email,
            'displayName' => (string) $account->account_name,
        ];
    }

    /**
     * Build the `Quota` contract (resources/js/types/contracts.ts) for the user.
     *
     * @return array{usedToday: int, limit: int, remaining: int, resetsAt: string}
     */
    public function quota(User $user): array
    {
        $limit = $this->plans->for($user)->dailyLimit;
        $usedToday = $user->applications()->countedToday()->count();

        return [
            'usedToday' => $usedToday,
            'limit' => $limit,
            'remaining' => max(0, $limit - $usedToday),
            'resetsAt' => now(config('app.timezone'))->addDay()->startOfDay()->toIso8601String(),
        ];
    }
}
