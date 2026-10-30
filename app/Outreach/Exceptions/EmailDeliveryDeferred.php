<?php

namespace App\Outreach\Exceptions;

use RuntimeException;

/**
 * The message was provably not delivered: retry with backoff.
 */
class EmailDeliveryDeferred extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The email was not delivered; it can be retried.');
    }
}
