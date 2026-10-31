<?php

namespace App\Contacts\Support;

class SmtpVerdict
{
    /**
     * Reply text that marks a rejection as a block/policy decision about us, not about the mailbox.
     */
    private const string BLOCK_PATTERN = '/spamhaus|blocked|blacklist|blocklist|policy|reputation|dnsbl|not allowed/i';

    /**
     * True when the server accepted the recipient (2xx).
     */
    public static function isAccepted(?int $code): bool
    {
        return $code !== null && $code >= 200 && $code < 300;
    }

    /**
     * True when the reply is a 5xx unknown-mailbox answer, null when it is inconclusive
     * (no reply, not a 5xx, or a block/policy decision about us).
     */
    public static function rejectsMailbox(?int $code, ?string $text): ?bool
    {
        if ($code === null || $code < 500 || $code >= 600) {
            return null;
        }

        $text = (string) $text;

        // Enhanced status first: 5.7.x is a policy decision about us, while 5.1.x and Exchange's 5.4.1
        // ("Recipient address rejected: Access denied") are plain unknown-mailbox answers.
        if (preg_match('/^5\.7\.\d+/', $text) === 1) {
            return null;
        }

        if (preg_match('/^5\.(?:1\.\d+|4\.1)\b/', $text) === 1) {
            return true;
        }

        return preg_match(self::BLOCK_PATTERN, $text) === 1 ? null : true;
    }
}
