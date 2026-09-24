<?php

namespace App\Outreach\Support;

use App\Models\JobPosting;
use App\Models\User;

final class ApplicationTemplateRenderer
{
    public const ALLOWED_VARIABLES = ['company', 'job_title', 'job_location', 'job_url', 'client_name'];

    public const DEFAULT_SUBJECT = 'Application — {{ job_title }}';

    public const DEFAULT_BODY = "Hello {{ company }} team,\n\nI'd like to apply for the '{{ job_title }}' position {{ job_url }}). My CV is attached.\n\nThank you for your time.\n\nBest regards,\n{{ client_name }}";

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

    /**
     * @return array<string, string>
     */
    public static function variablesFor(User $user, JobPosting $posting): array
    {
        $location = $posting->location;

        if ($location === null || $location === '') {
            $location = $posting->is_remote ? 'Remote' : '';
        }

        return [
            'company' => $posting->company->name ?? $posting->company_name,
            'job_title' => $posting->title,
            'job_location' => $location,
            'job_url' => $posting->url,
            'client_name' => $user->name,
        ];
    }

    /**
     * Replace allowed variables in a single pass (any inner whitespace, same
     * pattern as unknownVariables()). Unknown names are left untouched and
     * substituted values are never re-scanned. Never compiled by Blade.
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*(.*?)\s*\}\}/',
            fn(array $match): string => in_array(trim($match[1]), self::ALLOWED_VARIABLES, true)
                ? (string) ($variables[trim($match[1])] ?? '')
                : $match[0],
            $template,
        ) ?? $template;
    }

    public static function html(string $text): string
    {
        return nl2br(e($text));
    }
}
