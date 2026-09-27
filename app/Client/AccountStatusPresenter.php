<?php

namespace App\Client;

use App\Enums\ConnectedIntegrationStatus;
use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use App\Plans\PlanCatalog;

final class AccountStatusPresenter
{
    public function __construct(
        private PlanCatalog $plans,
        private CanSendApplications $eligibility,
    ) {}

    /**
     * Build the `AccountStatus` contract (resources/js/types/contracts.ts) for the user.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $plan = $this->plans->for($user);

        $usedToday = $user->applications()->countedToday()->count();

        $gmail = $user->gmailIntegration;
        $gmailState = match ($gmail?->status) {
            ConnectedIntegrationStatus::Connected => 'connected',
            ConnectedIntegrationStatus::ReauthorizationRequired => 'reauthorization_required',
            ConnectedIntegrationStatus::Disconnected, null => 'disconnected',
        };
        $gmailEmail = $gmail !== null && $gmailState === 'connected' ? $gmail->account_email : null;

        $preference = $user->jobPreference()->first();
        $hasCv = $this->eligibility->hasCv($preference);

        $steps = [
            ['key' => 'basics', 'done' => $user->country !== null && $user->timezone !== null],
            ['key' => 'gmail', 'done' => $gmailState === 'connected'],
            ['key' => 'profile', 'done' => $hasCv && $this->eligibility->hasValidTemplate($preference)],
            ['key' => 'preferences', 'done' => $preference !== null],
        ];

        return [
            'plan' => [
                'key' => $plan->key->value,
                'name' => $plan->name,
                'mode' => $plan->mode,
                'dailyLimit' => $plan->dailyLimit,
            ],
            'quota' => [
                'usedToday' => $usedToday,
                'limit' => $plan->dailyLimit,
                'remaining' => max(0, $plan->dailyLimit - $usedToday),
                'resetsAt' => now(config('app.timezone'))->addDay()->startOfDay()->toIso8601String(),
            ],
            'gmail' => [
                'state' => $gmailState,
                'accountEmail' => $gmailEmail,
            ],
            'sending' => [
                'paused' => false,
                'autoPausedReason' => null,
            ],
            'onboarding' => [
                'complete' => collect($steps)->every(fn (array $step): bool => $step['done']),
                'steps' => $steps,
            ],
            'profiles' => [
                // Spec B.6: temporary rule — the legacy CV activates both languages until per-language profiles exist.
                'activeLanguages' => $hasCv ? ['en', 'pt'] : [],
            ],
            'region' => $user->region->value,
            'country' => $user->country,
            'timezone' => $user->timezone,
            'unreadNotifications' => $user->unreadNotifications()->count(),
        ];
    }
}
