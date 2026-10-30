<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The SMTP connection test failed (address guard, connection, TLS, missing AUTH or login).
 * Fixed message: never carries server text, the username or the password.
 */
class SmtpConnectionFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The SMTP connection test failed.');
    }
}
