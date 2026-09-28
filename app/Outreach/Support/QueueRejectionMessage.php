<?php

namespace App\Outreach\Support;

use App\Outreach\Actions\QueueApplication;

/**
 * Maps a {@see QueueApplication} rejection reason to a translated sentence a client may see.
 * Unknown reasons (e.g. a missing posting) fall back to a generic message.
 */
final class QueueRejectionMessage
{
    /**
     * @var array<string, string>
     */
    private const EXACT = [
        QueueApplication::REJECT_NO_MATCH => 'queue.reject.no_match',
        QueueApplication::REJECT_NO_RECIPIENT => 'queue.reject.no_recipient',
        QueueApplication::REJECT_ALREADY_APPLIED => 'queue.reject.already_applied',
        QueueApplication::REJECT_NO_PROFILE => 'queue.reject.no_profile',
    ];

    public static function for(string $reason): string
    {
        $key = self::EXACT[$reason] ?? (str_starts_with($reason, QueueApplication::REJECT_NOT_ELIGIBLE_PREFIX)
            ? 'queue.reject.not_eligible'
            : 'queue.reject.generic');

        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
