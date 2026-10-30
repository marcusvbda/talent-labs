<?php

namespace App\Outreach\Exceptions;

use RuntimeException;

/**
 * The outcome of the send is unknown: the message may have left.
 */
class EmailDeliveryUnconfirmed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The email delivery could not be confirmed.');
    }
}
