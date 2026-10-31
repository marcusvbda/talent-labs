<?php

namespace App\Contacts\Support;

use Illuminate\Support\Str;

class HiringPowerTitle
{
    private const string PATTERN = '/\b(?:co-?\s?founder|founder|ceo|cto|chief executive officer|chief technology officer|head of (?:engineering|people|talent))\b/';

    /**
     * True for titles that usually hold hiring power: founder, co-founder, CEO, CTO, head of
     * engineering, head of people, head of talent. A `hiring_manager` source contact always counts
     * on its own, regardless of title.
     */
    public static function matches(?string $title): bool
    {
        if ($title === null || trim($title) === '') {
            return false;
        }

        return preg_match(self::PATTERN, strtolower(Str::ascii($title))) === 1;
    }
}
