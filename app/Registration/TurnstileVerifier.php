<?php

namespace App\Registration;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Confirms a Cloudflare Turnstile token server-side. Any failure to reach
 * Cloudflare counts as not passed, so the check fails closed.
 */
final class TurnstileVerifier
{
    private const string ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function passes(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, [
                'secret' => config('talent.registration.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return $response->json('success') === true;
    }
}
