<?php

namespace App\Collection\Support;

class PostingTitle
{
    /**
     * A title a job seeker can read: no control characters / U+FFFD (truncated or
     * garbled feed text) and at least two letters or digits.
     */
    public static function isUsable(?string $title): bool
    {
        $title = trim((string) $title);

        if (preg_match('/[\x{0080}-\x{009F}\x{FFFD}]/u', $title) === 1) {
            return false;
        }

        return preg_match_all('/[\p{L}\p{N}]/u', $title) >= 2;
    }

    /**
     * Stored until the AI profile extraction provides a normalized title.
     */
    public static function placeholder(string $companyName): string
    {
        return 'Open position at '.$companyName;
    }

    /**
     * True when the stored title is missing, unreadable or still the placeholder.
     */
    public static function needsReplacement(string $title, string $companyName): bool
    {
        return ! self::isUsable($title) || $title === self::placeholder($companyName);
    }
}
