<?php

namespace App\Outreach\Exceptions;

use RuntimeException;

/**
 * The provider refused the recipient address.
 */
class EmailRecipientRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The recipient address was rejected.');
    }
}
