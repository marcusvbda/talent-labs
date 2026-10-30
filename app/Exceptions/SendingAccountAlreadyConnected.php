<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Another sending plugin's integration is active; the user must disconnect it first.
 */
class SendingAccountAlreadyConnected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another sending account is already connected.');
    }
}
