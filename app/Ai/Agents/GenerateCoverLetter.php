<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\OutreachLimits;
use App\Outreach\Support\ApplicationTemplateRenderer;
use App\Outreach\Support\ClientSafeText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Writes the cover letter that replaces the marker in the client's email body for one posting.
 * Provider and model come from `talent.ai.cover_letter`, passed by the caller. No link or email
 * address ever reaches the model: posting values and the rendered body are redacted.
 * Expects `company` and `profile` loaded on the posting.
 */
class GenerateCoverLetter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use BuildsCompactAgentContext;
    use Promptable;

    public const CACHE_SCHEMA_VERSION = 'cover-letter-v1';

    public const CACHE_KEY = 'cover_letter_generation';

    public const MARKER = '[[COVER_LETTER]]';

    public const MAX_DESCRIPTION_CHARS = 4000;

    public function __construct(
        private readonly User $user,
        private readonly JobPosting $posting,
        private readonly ApplicationProfile $profile,
    ) {}

    public function instructions(): string
    {
        return 'Write the cover letter for the client\'s application email from the context (TOON format). email_body is the email as it will be sent; write only the text that replaces '.self::MARKER.' in it: no greeting, no sign-off, no subject line, no URLs, no email addresses, no placeholders. Write in the language given by language ("en" English, "pt" Portuguese). Aim at '.OutreachLimits::COVER_LETTER_TARGET_WORDS.' words and stay under '.OutreachLimits::COVER_LETTER_MAX_CHARS.' characters. Make one concrete point about the job (from the posting fields and description) and one concrete point about the client. manual_cover_letter is the only source of facts about the client. client_stack only guides which of those facts to emphasize; it is never a claim of experience. Never invent experience, years, employers, degrees or numbers.';
    }

    public function context(): string
    {
        $postingProfile = $this->posting->profile;
        $variables = ApplicationTemplateRenderer::variablesFor($this->user, $this->posting, $this->profile);
        $linkLabels = $this->linkLabels($this->profile->links);
        $description = $this->escapedPlainText($this->posting->description_html ?? $this->posting->description_text) ?? '';

        return $this->compactContext([
            'company' => ClientSafeText::redact($variables['company']),
            'normalized_title' => $postingProfile?->normalized_title,
            'seniority' => $postingProfile?->seniority,
            'stack' => $postingProfile?->stack,
            'summary' => $postingProfile?->summary,
            'description' => mb_substr(ClientSafeText::redact($description), 0, self::MAX_DESCRIPTION_CHARS),
            'language' => $this->profile->language->value,
            'manual_cover_letter' => ClientSafeText::redact($variables['cover_letter']),
            'link_labels' => $linkLabels,
            'client_stack' => $this->user->jobPreference?->stack,
            'email_body' => $this->emailBody($variables, $linkLabels),
        ]);
    }

    /**
     * Same reasoning trade-off as the extraction agents, but configurable: an empty
     * effort leaves the provider default.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $effort = config('talent.ai.cover_letter.reasoning_effort');

        return is_string($effort) && $effort !== '' ? ['reasoning' => ['effort' => $effort]] : [];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'cover_letter' => $schema->string()->max(OutreachLimits::COVER_LETTER_MAX_CHARS)->required(),
            'language' => $schema->string()->enum(['en', 'pt'])->required(),
        ];
    }

    /**
     * The email body with the marker where the letter goes. The job link line is dropped
     * and links appear by label only; the result is redacted again because the template
     * itself may hold a link or an address.
     *
     * @param  array<string, string>  $variables
     * @param  list<string>  $linkLabels
     */
    private function emailBody(array $variables, array $linkLabels): string
    {
        $variables['cover_letter'] = self::MARKER;
        $variables['job_url'] = '';
        $variables['links'] = implode("\n", $linkLabels);
        $variables['company'] = ClientSafeText::redact($variables['company']);
        $variables['job_title'] = ClientSafeText::redact($variables['job_title']);
        $variables['job_location'] = ClientSafeText::redact($variables['job_location']);

        return ClientSafeText::redact(ApplicationTemplateRenderer::render((string) $this->profile->email_body, $variables));
    }

    /**
     * Labels of the complete links (same rule as ApplicationTemplateRenderer::linksText()), redacted.
     * Stored entries are not trusted to have both keys as strings.
     *
     * @param  list<array{label?: mixed, url?: mixed}>|null  $links
     * @return list<string>
     */
    private function linkLabels(?array $links): array
    {
        $labels = [];

        foreach ($links ?? [] as $link) {
            $label = is_string($link['label'] ?? null) ? trim($link['label']) : '';
            $url = is_string($link['url'] ?? null) ? trim($link['url']) : '';

            if ($label !== '' && $url !== '') {
                $labels[] = ClientSafeText::redact($label);
            }
        }

        return $labels;
    }
}
