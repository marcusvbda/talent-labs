<?php

namespace App\Outreach\Exceptions;

use RuntimeException;

/**
 * The provider's sending or rate limit was reached.
 */
class EmailProviderLimitReached extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The email provider sending limit was reached.');
    }
}
