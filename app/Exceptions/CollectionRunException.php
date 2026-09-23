<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A collection run operation was refused; the message is safe to show to users.
 */
class CollectionRunException extends RuntimeException {}
