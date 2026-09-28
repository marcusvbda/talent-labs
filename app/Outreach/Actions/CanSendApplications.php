<?php

namespace App\Outreach\Actions;

use App\Enums\ConnectedIntegrationStatus;
use App\Models\ApplicationProfile;
use App\Models\User;
use App\Outreach\Data\SendEligibility;
use App\Plans\PlanCatalog;

class CanSendApplications
{
    public const int MAX_SUBJECT_LENGTH = 200;

    public const int MAX_BODY_LENGTH = 5000;

    public function __construct(private PlanCatalog $plans) {}

    public function check(User $user): SendEligibility
    {
        $unmet = [];

        if (! $user->isActive()) {
            $unmet[] = 'Your account is not active.';
        }

        $gmail = $user->gmailIntegration()->first();

        if ($gmail === null || $gmail->status === ConnectedIntegrationStatus::Disconnected) {
            $unmet[] = 'Connect your Gmail account.';
        } elseif ($gmail->status === ConnectedIntegrationStatus::ReauthorizationRequired) {
            $unmet[] = 'Reconnect your Gmail account.';
        }

        if (ApplicationProfile::activeCompleteLanguagesFor($user) === []) {
            $unmet[] = 'Create an application profile.';
        }

        $limit = $this->plans->for($user)->dailyLimit;
        $sentToday = $user->applications()->countedToday()->count();
        $remaining = max(0, $limit - $sentToday);

        if ($remaining === 0) {
            $unmet[] = 'Daily limit of '.$limit.' applications reached.';
        }

        return new SendEligibility($unmet, $sentToday, $remaining);
    }
}
