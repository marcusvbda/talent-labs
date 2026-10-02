<?php

namespace App\Client;

use App\Http\Resources\Client\JobCardResource;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Outreach\Support\ClientSafeText;

/**
 * The `ReviewDraft` contract. Expects `company` and `profile` loaded on the posting.
 * Posting-derived values are redacted and the job link stays as the `{{ job_url }}`
 * token; the recipient address and the real job URL are never included.
 */
final class ReviewDraftPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forUser(User $user, JobPosting $posting, ApplicationProfile $profile): array
    {
        $posting->loadMissing('collectionRun');

        $variables = ApplicationTemplateRenderer::variablesFor($user, $posting, $profile);

        $variables['job_url'] = ClientSafeText::JOB_URL_TOKEN;
        $variables['company'] = ClientSafeText::redact($variables['company']);
        $variables['job_title'] = ClientSafeText::redact($variables['job_title']);
        $variables['job_location'] = ClientSafeText::redact($variables['job_location']);

        return [
            'job' => new JobCardResource($posting),
            'language' => $profile->language,
            'subject' => ApplicationTemplateRenderer::render((string) $profile->email_subject, $variables),
            'body' => ApplicationTemplateRenderer::render((string) $profile->email_body, $variables),
            'cvFileName' => $profile->cv_original_name,
            'recipientLabel' => __('review.recipient_label', ['company' => $posting->company->name ?? '']),
        ];
    }
}
