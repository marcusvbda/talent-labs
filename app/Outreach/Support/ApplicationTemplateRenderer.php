<?php

namespace App\Outreach\Support;

use App\Enums\ApplicationLanguage;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;

final class ApplicationTemplateRenderer
{
    public const ALLOWED_VARIABLES = ['company', 'job_title', 'job_location', 'job_url', 'client_name', 'cover_letter', 'links'];

    private const DEFAULT_SUBJECT_EN = 'Application: {{ job_title }}';

    private const DEFAULT_BODY_EN = "Hello {{ company }} team,\n\nI'm writing to apply for the {{ job_title }} position ({{ job_url }}).\n\n{{ cover_letter }}\n\nMy CV is attached. Thank you for your time.\n\nBest regards,\n{{ client_name }}";

    private const DEFAULT_SUBJECT_PT = 'Candidatura: {{ job_title }}';

    private const DEFAULT_BODY_PT = "Olá, equipe {{ company }},\n\nGostaria de me candidatar à vaga de {{ job_title }} ({{ job_url }}).\n\n{{ cover_letter }}\n\nMeu currículo está em anexo. Obrigado pelo seu tempo.\n\nAtenciosamente,\n{{ client_name }}";

    /**
     * Variable names used in the text that are not allowed.
     *
     * @return list<string>
     */
    public static function unknownVariables(string $text): array
    {
        preg_match_all('/\{\{\s*(.*?)\s*\}\}/', $text, $matches);

        return array_values(array_unique(array_diff($matches[1], self::ALLOWED_VARIABLES)));
    }

    public static function containsCoverLetterVariable(string $text): bool
    {
        return preg_match('/\{\{\s*cover_letter\s*\}\}/', $text) === 1;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public static function defaultsFor(ApplicationLanguage $language): array
    {
        return match ($language) {
            ApplicationLanguage::En => ['subject' => self::DEFAULT_SUBJECT_EN, 'body' => self::DEFAULT_BODY_EN],
            ApplicationLanguage::Pt => ['subject' => self::DEFAULT_SUBJECT_PT, 'body' => self::DEFAULT_BODY_PT],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(User $user, JobPosting $posting, ApplicationProfile $profile): array
    {
        $location = $posting->location;

        if ($location === null || $location === '') {
            $location = $posting->is_remote ? 'Remote' : '';
        }

        $base = [
            'company' => $posting->company->name ?? $posting->company_name,
            'job_title' => $posting->title,
            'job_location' => $location,
            'job_url' => (string) $posting->applicationUrl(),
            'client_name' => $user->name,
            'links' => self::linksText($profile->links),
        ];

        return [
            ...$base,
            'cover_letter' => self::render((string) $profile->cover_letter, $base),
        ];
    }

    /**
     * One "label : url" line per complete link, in entry order.
     *
     * @param  list<array{label?: mixed, url?: mixed}>|null  $links
     */
    public static function linksText(?array $links): string
    {
        $lines = [];

        foreach ($links ?? [] as $link) {
            $label = is_string($link['label'] ?? null) ? trim($link['label']) : '';
            $url = is_string($link['url'] ?? null) ? trim($link['url']) : '';

            if ($label === '' || $url === '') {
                continue;
            }

            $lines[] = "{$label} : {$url}";
        }

        return implode("\n", $lines);
    }

    /**
     * Replace allowed variables in a single pass (any inner whitespace, same
     * pattern as unknownVariables()). Unknown names are left untouched and
     * substituted values are never re-scanned. Never compiled by Blade.
     * Line endings are normalized, runs of blank lines collapse to one and
     * the result is trimmed.
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/',
            fn (array $match): string => in_array(trim($match[1]), self::ALLOWED_VARIABLES, true)
                ? (string) ($variables[trim($match[1])] ?? '')
                : $match[0],
            $template,
        ) ?? $template;

        $rendered = str_replace("\r\n", "\n", $rendered);
        $rendered = preg_replace('/\n(?:[ \t]*\n){2,}/', "\n\n", $rendered) ?? $rendered;

        return trim($rendered);
    }

    public static function html(string $text): string
    {
        return nl2br(e($text));
    }
}
