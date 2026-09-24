<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\BuildsCompactAgentContext;
use App\Models\JobPosting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
class ExtractJobPostingProfile implements Agent, HasStructuredOutput
{
    use BuildsCompactAgentContext;
    use Promptable;

    public const CACHE_SCHEMA_VERSION = 'posting-profile-v1';

    public const MAX_DESCRIPTION_CHARS = 4000;

    public const MODEL = 'gpt-4o-mini';

    public const CACHE_KEY = 'posting_profile_extraction';

    public const SENIORITIES = ['intern', 'junior', 'mid', 'senior', 'lead', 'unknown'];

    public function __construct(private readonly JobPosting $posting) {}

    public function instructions(): string
    {
        return 'Extract a job posting profile from the context (TOON format). Use only evidence in the context; never invent. normalized_title: clean role title. seniority: one of the allowed values, "unknown" if unclear. stack: canonical lowercase technology names. locations: countries, cities or regions as written (e.g. "Germany", "EU", "Worldwide"). is_remote: null if unclear. summary: one plain-text sentence.';
    }

    public function postingContext(): string
    {
        $this->posting->loadMissing('company');

        $description = $this->descriptionText() ?? '';

        return $this->compactContext([
            'title' => $this->posting->title,
            'company' => $this->posting->company->name ?? $this->posting->company_name,
            'location' => $this->posting->location,
            'is_remote' => $this->posting->is_remote,
            'employment_type' => $this->posting->employment_type,
            'department' => $this->posting->department,
            'tags' => $this->tags(),
            'description' => mb_substr($description, 0, self::MAX_DESCRIPTION_CHARS),
        ]);
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'normalized_title' => $schema->string()->max(120)->nullable()->required(),
            'seniority' => $schema->string()->enum(self::SENIORITIES)->required(),
            'stack' => $schema->array()->items($schema->string()->max(40))->max(15)->required(),
            'locations' => $schema->array()->items($schema->string()->max(40))->max(5)->required(),
            'is_remote' => $schema->boolean()->nullable()->required(),
            'summary' => $schema->string()->max(300)->nullable()->required(),
        ];
    }

    /**
     * Some sources deliver the description as entity-escaped HTML
     * (`&lt;p&gt;...`), so the first pass only decodes it back into markup;
     * a second pass strips that markup.
     */
    private function descriptionText(): ?string
    {
        $text = $this->plainText($this->posting->description_html ?? $this->posting->description_text);

        if ($text !== null && preg_match('#</?[a-z][a-z0-9]*\b[^>]*>#i', $text) === 1) {
            return $this->plainText($text);
        }

        return $text;
    }

    /**
     * Source tags as a list of strings: `raw.tags` when it is a list of
     * strings, otherwise `raw.jobIndustry` (a string or a list of strings).
     *
     * @return list<string>|null
     */
    private function tags(): ?array
    {
        $raw = $this->posting->raw;
        $tags = $raw['tags'] ?? null;

        if (is_array($tags) && $tags !== [] && array_all($tags, fn (mixed $tag): bool => is_string($tag))) {
            return array_values($tags);
        }

        $industry = $raw['jobIndustry'] ?? null;

        if (is_string($industry)) {
            $industry = [$industry];
        }

        if (! is_array($industry)) {
            return null;
        }

        $industries = array_values(array_filter(
            array_map(
                fn (mixed $item): string => is_string($item) ? trim(html_entity_decode($item, ENT_QUOTES | ENT_HTML5)) : '',
                $industry,
            ),
            fn (string $item): bool => $item !== '',
        ));

        return $industries === [] ? null : $industries;
    }
}
