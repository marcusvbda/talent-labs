<?php

namespace App\Outreach;

final class OutreachLimits
{
    public const MAX_POSTING_AGE_DAYS = 14;

    /**
     * Length of a cover letter: the AI aims at the word target; the character cap applies to every letter.
     */
    public const int COVER_LETTER_TARGET_WORDS = 100;

    public const int COVER_LETTER_MAX_CHARS = 800;

    public const RECIPIENT_PRIORITY = ['careers', 'jobs', 'hr', 'talent', 'recruiting', 'people'];

    /**
     * Mailbox local parts that reach a shared inbox rather than a named person.
     */
    public const GENERIC_LOCAL_PARTS = ['careers', 'jobs', 'hr', 'info', 'contact', 'talent', 'recruiting', 'people'];
}
