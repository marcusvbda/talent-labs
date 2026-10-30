<?php

namespace App\Outreach\Exceptions;

use RuntimeException;

/**
 * The provider refused the message because it is too large.
 */
class EmailMessageTooLarge extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The message is too large for the email provider.');
    }
}
