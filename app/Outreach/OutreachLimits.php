<?php

namespace App\Outreach;

final class OutreachLimits
{
    public const MAX_POSTING_AGE_DAYS = 14;

    public const RECIPIENT_PRIORITY = ['careers', 'jobs', 'hr', 'talent', 'recruiting', 'people'];

    /**
     * Mailbox local parts that reach a shared inbox rather than a named person.
     */
    public const GENERIC_LOCAL_PARTS = ['careers', 'jobs', 'hr', 'info', 'contact', 'talent', 'recruiting', 'people'];
}
