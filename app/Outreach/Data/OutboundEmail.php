<?php

namespace App\Outreach\Data;

/**
 * A built application email: the raw RFC 822 message plus the envelope a
 * transport may need. $messageId is the Message-ID header value, without
 * angle brackets.
 */
final readonly class OutboundEmail
{
    public function __construct(
        public string $rawMime,
        public string $messageId,
        public string $from,
        public string $recipient,
    ) {}
}
