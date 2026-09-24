<?php

namespace App\Outreach\Actions;

use App\Enums\ConnectedIntegrationStatus;
use App\Models\JobPreference;
use App\Models\User;
use App\Outreach\Data\SendEligibility;
use App\Outreach\OutreachLimits;
use App\Outreach\Support\ApplicationTemplateRenderer;
use Illuminate\Support\Facades\Storage;

class CanSendApplications
{
    public const int MAX_SUBJECT_LENGTH = 200;

    public const int MAX_BODY_LENGTH = 5000;

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

        $preference = $user->jobPreference()->first();

        if (! $this->hasCv($preference)) {
            $unmet[] = 'Upload your CV.';
        }

        if (! $this->hasValidTemplate($preference)) {
            $unmet[] = 'Fix your email template.';
        }

        $sentToday = $user->applications()->countedToday()->count();
        $remaining = max(0, OutreachLimits::DAILY_SEND_LIMIT - $sentToday);

        if ($remaining === 0) {
            $unmet[] = 'Daily limit of '.OutreachLimits::DAILY_SEND_LIMIT.' applications reached.';
        }

        return new SendEligibility($unmet, $sentToday, $remaining);
    }

    private function hasCv(?JobPreference $preference): bool
    {
        $path = $preference?->cv_path;

        return filled($path) && Storage::disk('local')->exists($path);
    }

    private function hasValidTemplate(?JobPreference $preference): bool
    {
        $subject = trim((string) $preference?->email_subject);
        $body = trim((string) $preference?->email_body);

        return $subject !== ''
            && $body !== ''
            && mb_strlen($subject) <= self::MAX_SUBJECT_LENGTH
            && mb_strlen($body) <= self::MAX_BODY_LENGTH
            && ApplicationTemplateRenderer::unknownVariables($subject) === []
            && ApplicationTemplateRenderer::unknownVariables($body) === [];
    }
}
