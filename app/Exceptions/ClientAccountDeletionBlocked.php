<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The user row could not be deleted because other records still restrict it
 * (e.g. invitations the user created). Carries no database details.
 */
class ClientAccountDeletionBlocked extends RuntimeException {}
