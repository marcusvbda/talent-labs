<?php

namespace App\Invitations;

use App\Models\Invitation;
use Illuminate\Support\Str;

/**
 * Mints and looks up invitation tokens. Only the SHA-256 hash is stored;
 * the raw token is never persisted or logged.
 */
final class InvitationTokens
{
    public const TOKEN_LENGTH = 40;

    public static function generate(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function find(?string $token): ?Invitation
    {
        if ($token === null || trim($token) === '') {
            return null;
        }

        return Invitation::query()->where('token_hash', self::hash($token))->first();
    }

    public static function link(string $token): string
    {
        return route('register', ['invite' => $token]);
    }
}
