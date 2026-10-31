<?php

namespace App\Ai\Support;

use App\Models\ApplicationProfile;
use App\Models\User;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Plans\PlanCatalog;

/**
 * Whether the cover letter is written by AI for this user: the plan includes AI writing,
 * the email body uses the cover letter variable and there is a manual letter to draw from.
 */
final class CoverLetterEligibility
{
    public static function applies(User $user, ApplicationProfile $profile): bool
    {
        return app(PlanCatalog::class)->for($user)->aiWriting
            && ApplicationTemplateRenderer::containsCoverLetterVariable((string) $profile->email_body)
            && trim((string) $profile->cover_letter) !== '';
    }
}
