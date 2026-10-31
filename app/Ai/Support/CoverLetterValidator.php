<?php

namespace App\Ai\Support;

use App\Outreach\OutreachLimits;
use App\Outreach\Support\ClientSafeText;

/**
 * Strict check of a generated cover letter before it is used. A letter that fails is
 * rejected as a whole, never truncated or cleaned up.
 */
final class CoverLetterValidator
{
    private const BARE_DOMAIN = '/\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:com|org|net|io|co|ai|dev|app|eu|uk|de|br|fr|es|pt|nl|jobs|careers|tech)\b/iu';

    /**
     * The trimmed letter, or null when the output must not be used.
     *
     * @param  array<string, mixed>  $output
     */
    public static function accepts(array $output, string $language): ?string
    {
        $letter = $output['cover_letter'] ?? null;

        if (! is_string($letter)) {
            return null;
        }

        $letter = trim($letter);

        if ($letter === '' || mb_strlen($letter) > OutreachLimits::COVER_LETTER_MAX_CHARS) {
            return null;
        }

        // Template variables or the marker would reach the email unrendered.
        if (str_contains($letter, '{{') || str_contains($letter, '}}') || str_contains($letter, '[[')) {
            return null;
        }

        if (ClientSafeText::redact($letter) !== $letter || preg_match(self::BARE_DOMAIN, $letter) === 1) {
            return null;
        }

        if (($output['language'] ?? null) !== $language) {
            return null;
        }

        return $letter;
    }
}
