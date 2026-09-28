<?php

namespace App\Client;

use App\Enums\ConnectedIntegrationStatus;
use App\Enums\SendingPauseReason;
use App\Models\ApplicationProfile;
use App\Models\User;
use App\Plans\PlanCatalog;

final class AccountStatusPresenter
{
    public function __construct(
        private PlanCatalog $plans,
    ) {}

    /**
     * Build the `AccountStatus` contract (resources/js/types/contracts.ts) for the user.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $plan = $this->plans->for($user);

        $gmail = $user->gmailIntegration;
        $gmailState = match ($gmail?->status) {
            ConnectedIntegrationStatus::Connected => 'connected',
            ConnectedIntegrationStatus::ReauthorizationRequired => 'reauthorization_required',
            ConnectedIntegrationStatus::Disconnected, null => 'disconnected',
        };
        $gmailEmail = $gmail !== null && $gmailState === 'connected' ? $gmail->account_email : null;

        $preference = $user->jobPreference()->first();
        $languages = ApplicationProfile::activeCompleteLanguagesFor($user);

        $steps = [
            ['key' => 'basics', 'done' => $user->country !== null && $user->timezone !== null],
            ['key' => 'gmail', 'done' => $gmailState === 'connected'],
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
            'gmail' => [
                'state' => $gmailState,
                'accountEmail' => $gmailEmail,
            ],
            'sending' => [
                'paused' => $user->isSendingPaused(),
                'autoPausedReason' => in_array($user->sending_pause_reason, [SendingPauseReason::ReauthorizationRequired, SendingPauseReason::RepeatedFailures], true)
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
