<?php

namespace App\Outreach\Contracts;

/**
 * A transport that refuses messages above a raw MIME size.
 */
interface LimitsMessageSize
{
    /**
     * The largest raw MIME message, in bytes, the transport accepts.
     */
    public function maxRawMimeBytes(): int;
}
