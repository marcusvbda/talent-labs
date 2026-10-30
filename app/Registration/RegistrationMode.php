<?php

namespace App\Registration;

/**
 * Single switch for how people sign up: `open` lets anyone register,
 * anything else (default `invite`) requires an invitation, so a typo fails closed.
 */
final class RegistrationMode
{
    public static function isOpen(): bool
    {
        return config('talent.registration.mode') === 'open';
    }
}
