<?php

namespace App\Outreach\Support;

/**
 * Text shown to a client must not let them apply outside the platform, so links and
 * email addresses are replaced before display. The emails we send are never redacted.
 */
final class ClientSafeText
{
    public const LINK = '[link]';

    public const EMAIL = '[email]';

    public const JOB_URL_TOKEN = '{{ job_url }}';

    public static function redact(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Emails first, so the local part of an address is never mistaken for part of a link.
        $text = preg_replace('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu', self::EMAIL, $text) ?? $text;

        // Full links (scheme or www).
        $text = preg_replace('~(?:https?://|www\.)[^\s<>"\')\]]+~iu', self::LINK, $text) ?? $text;

        // Bare domains that point somewhere: a common TLD followed by a path (company.com/careers).
        return preg_replace('~\b[a-z0-9\-]+(?:\.[a-z0-9\-]+)*\.(?:com|org|net|io|co|ai|dev|app|eu|uk|de|br|fr|es|pt|nl|jobs|careers|tech)/[^\s<>"\')\]]*~iu', self::LINK, $text) ?? $text;
    }

    /**
     * Replaces the job's own link with a `{{ job_url }}` token before redacting, so the
     * client sees where the link goes in their message without seeing the link itself.
     */
    public static function tokenizeJobUrl(string $text, ?string $jobUrl): string
    {
        if ($jobUrl !== null && $jobUrl !== '') {
            $text = str_replace($jobUrl, self::JOB_URL_TOKEN, $text);
        }

        return self::redact($text);
    }
}
