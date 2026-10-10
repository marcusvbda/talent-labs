<?php

namespace App\Outreach\Support;

use App\Enums\ApplicationLanguage;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;

final class ApplicationTemplateRenderer
{
    public const ALLOWED_VARIABLES = ['company', 'job_title', 'job_location', 'job_url', 'client_name', 'cover_letter', 'links'];

    private const DEFAULT_SUBJECT_EN = 'Application: {{ job_title }} – {{ client_name }}';

    private const DEFAULT_BODY_EN = "Hi {{ company }} team,\n\n{{ cover_letter }}\n\nJob posting: {{ job_url }}\n\nMy CV is attached.\n\n{{ links }}\n\nWorth a 15-minute chat this week about this role?\n\nBest regards,\n{{ client_name }}";

    private const DEFAULT_SUBJECT_PT = 'Aplicação: {{ job_title }} – {{ client_name }}';

    private const DEFAULT_BODY_PT = "Olá, equipe {{ company }},\n\n{{ cover_letter }}\n\nVaga: {{ job_url }}\n\nMeu currículo está em anexo.\n\n{{ links }}\n\nPodemos conversar 15 minutos esta semana sobre esta vaga?\n\nAtenciosamente,\n{{ client_name }}";

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
     * the result is trimmed. When `job_url` is given as '', the link line is
     * dropped first (see dropEmptyJobUrlLines()).
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        if (array_key_exists('job_url', $variables) && $variables['job_url'] === '') {
            $template = self::dropEmptyJobUrlLines($template);
        }

        $rendered = preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/',
            fn(array $match): string => in_array(trim($match[1]), self::ALLOWED_VARIABLES, true)
                ? (string) ($variables[trim($match[1])] ?? '')
                : $match[0],
            $template,
        ) ?? $template;

        $rendered = str_replace("\r\n", "\n", $rendered);
        $rendered = preg_replace('/\n(?:[ \t]*\n){2,}/', "\n\n", $rendered) ?? $rendered;

        return trim($rendered);
    }

    /**
     * For each line with `{{ job_url }}`: remove the placeholder (and an enclosing
     * ` (...)`); drop the line when nothing or only a short label ("Job posting:")
     * is left, otherwise collapse double spaces and the space before `.`/`,`.
     */
    private static function dropEmptyJobUrlLines(string $template): string
    {
        $placeholder = '\{\{\s*job_url\s*\}\}';
        $lines = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $template)) as $line) {
            if (preg_match("/{$placeholder}/", $line) !== 1) {
                $lines[] = $line;

                continue;
            }

            $line = preg_replace("/\s*\(\s*{$placeholder}\s*\)/", '', $line) ?? $line;
            $line = preg_replace("/{$placeholder}/", '', $line) ?? $line;
            $rest = trim($line);

            if ($rest === '' || preg_match('/^[\p{L} ]{1,30}:$/u', $rest) === 1) {
                continue;
            }

            $line = preg_replace('/ {2,}/', ' ', $line) ?? $line;
            $lines[] = preg_replace('/ +([.,])/', '$1', $line) ?? $line;
        }

        return implode("\n", $lines);
    }

    public static function html(string $text): string
    {
        return nl2br(e($text));
    }
}
