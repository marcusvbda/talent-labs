<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\BuildsCompactAgentContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Lists the people named in a company's own pages and a job posting, with their titles.
 * Evidence only: never invents people and never returns emails.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-5-nano')]
class ExtractCompanyPeople implements Agent, HasProviderOptions, HasStructuredOutput
{
    use BuildsCompactAgentContext;
    use Promptable;

    public const MODEL = 'gpt-5-nano';

    public const CACHE_KEY = 'company_people_extraction';

    public const MAX_PAGES_CHARS = 12000;

    public const MAX_POSTING_CHARS = 4000;

    /**
     * @param  array<string, string>  $pages  path => plain text of the company's own pages
     */
    public function __construct(
        private readonly string $companyName,
        private readonly array $pages,
        private readonly ?string $postingText,
    ) {}

    public function instructions(): string
    {
        return 'List the real people named in the context (TOON format): the company\'s own web pages and one of its job postings. Use only evidence in the context; never invent or guess people, never complete partial names, never return email addresses. name: the person\'s full name exactly as written. title: their role or job title at this company as written, null if none is stated. Return an empty list when no person is named.';
    }

    public function context(): string
    {
        $pages = mb_substr(implode(' ', array_map(
            fn (string $path, string $text): string => "[{$path}] {$text}",
            array_keys($this->pages),
            array_values($this->pages),
        )), 0, self::MAX_PAGES_CHARS);

        return $this->compactContext([
            'company' => $this->companyName,
            'pages' => $pages,
            'posting' => mb_substr($this->plainText($this->postingText) ?? '', 0, self::MAX_POSTING_CHARS),
        ]);
    }

    /**
     * Same reasoning effort as the posting profile agent: extraction gains nothing from more.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['reasoning' => ['effort' => 'minimal']];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'people' => $schema->array()->items($schema->object([
                'name' => $schema->string()->max(120)->required(),
                'title' => $schema->string()->max(120)->nullable()->required(),
            ])->withoutAdditionalProperties())->max(20)->required(),
        ];
    }
}
