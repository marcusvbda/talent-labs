<?php

namespace App\Client;

use App\Enums\ApplicationLanguage;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Outreach\Support\ClientSafeText;

final class TemplatePreviewPresenter
{
    /**
     * Build the `TemplatePreview` contract (resources/js/types/contracts.ts) for a draft
     * template, without saving anything. The sample job is the newest pool posting in the
     * language, ignoring the language-completeness rule (the client is usually drafting the
     * profile that would unlock it), or a fixed unsaved example when the pool has none.
     *
     * @return array{subject: string, body: string, sampleJob: array{company: string, title: string}}
     */
    public static function forUser(User $user, ApplicationLanguage $language, string $subject, string $body, string $coverLetter): array
    {
        $posting = MatchingJobPostings::forUser($user, withLanguageRule: false)
            ->where('job_posting_profiles.language', $language->value)
            ->with('company')
            ->reorder()
            ->orderByDesc('job_postings.first_seen_at')
            ->orderByDesc('job_postings.id')
            ->first() ?? self::fallbackPosting($language);

        $draftProfile = new ApplicationProfile;
        $draftProfile->language = $language;
        $draftProfile->cover_letter = $coverLetter;

        $variables = ApplicationTemplateRenderer::variablesFor($user, $posting, $draftProfile);

        return [
            'subject' => ClientSafeText::tokenizeJobUrl(ApplicationTemplateRenderer::render($subject, $variables), $posting->url),
            'body' => ClientSafeText::tokenizeJobUrl(ApplicationTemplateRenderer::render($body, $variables), $posting->url),
            'sampleJob' => [
                'company' => $posting->company->name ?? $posting->company_name,
                'title' => $posting->title,
            ],
        ];
    }

    /**
     * Never saved: only used to render the preview when the pool has no job in the language.
     */
    private static function fallbackPosting(ApplicationLanguage $language): JobPosting
    {
        $posting = new JobPosting;
        $posting->company_name = 'Acme Robotics';
        $posting->is_remote = true;
        $posting->url = 'https://example.com/jobs/backend-engineer';
        [$posting->title, $posting->location] = match ($language) {
            ApplicationLanguage::En => ['Backend Engineer', 'Remote'],
            ApplicationLanguage::Pt => ['Desenvolvedor Backend', 'Remoto'],
        };

        return $posting;
    }
}
