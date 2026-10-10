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
     * @param  array<mixed>  $links  Draft rows (`{label?: string|null, url?: string|null}`); incomplete ones are skipped.
     * @return array{subject: string, body: string, sampleJob: array{company: string, title: string}}
     */
    public static function forUser(User $user, ApplicationLanguage $language, string $subject, string $body, string $coverLetter, array $links = []): array
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
        $draftProfile->links = self::completeLinks($links);

        $variables = ApplicationTemplateRenderer::variablesFor($user, $posting, $draftProfile);

        $linksText = $variables['links'] ?? '';

        return [
            'subject' => self::clientSafe(ApplicationTemplateRenderer::render($subject, $variables), (string) $posting->applicationUrl(), $linksText),
            'body' => self::clientSafe(ApplicationTemplateRenderer::render($body, $variables), (string) $posting->applicationUrl(), $linksText),
            'sampleJob' => [
                'company' => $posting->company->name ?? $posting->company_name,
                'title' => $posting->title,
            ],
        ];
    }

    /**
     * Redact links/emails for the client, except the client's own `{{ links }}` text: it is
     * swapped for a one-off placeholder (no dots, no `@`, so redaction leaves it alone)
     * before redacting and restored afterward.
     */
    private static function clientSafe(string $rendered, ?string $jobUrl, string $linksText): string
    {
        if ($linksText === '') {
            return ClientSafeText::tokenizeJobUrl($rendered, $jobUrl);
        }

        $placeholder = 'LINKS'.bin2hex(random_bytes(16));

        $redacted = ClientSafeText::tokenizeJobUrl(str_replace($linksText, $placeholder, $rendered), $jobUrl);

        return str_replace($placeholder, $linksText, $redacted);
    }

    /**
     * Keep only draft rows with both a label and a URL, as `linksText()` would.
     *
     * @param  array<mixed>  $links
     * @return list<array{label: string, url: string}>
     */
    private static function completeLinks(array $links): array
    {
        $complete = [];

        foreach ($links as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $url = is_string($row['url'] ?? null) ? trim($row['url']) : '';

            if ($label !== '' && $url !== '') {
                $complete[] = ['label' => $label, 'url' => $url];
            }
        }

        return $complete;
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
