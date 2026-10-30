<?php

namespace App\Outreach\Exceptions;

use RuntimeException;

/**
 * The provider rejected the account's authorization or the consent was revoked.
 */
class EmailAuthorizationRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The email provider rejected the account authorization.');
    }
}
