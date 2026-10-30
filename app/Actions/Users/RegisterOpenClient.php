<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\Client\RegistrationAttempted;
use Closure;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Open-mode signup that reveals nothing about whether the address already has an
 * account: the caller always answers the same way, and every branch hashes the
 * password once so timing stays comparable.
 *
 * - New address: creates the account and fires `Registered` (first verification mail).
 * - Existing, unverified, active, non-admin: the newest signup wins (name, password,
 *   timezone replaced) and a fresh verification mail is sent; plan and invite untouched.
 * - Existing, verified or admin: a "someone tried to sign up" mail is sent.
 * - Existing, blocked: nothing changes, no mail.
 *
 * Mails to existing addresses are capped per address (3 per hour).
 */
final class RegisterOpenClient
{
    private const MAIL_ATTEMPTS = 3;

    private const MAIL_DECAY_SECONDS = 3600;

    public function __construct(private CreateClientAccount $createClientAccount) {}

    /**
     * @param  array{name: string, email: string, password: string, invite?: string|null, timezone: string}  $validated
     */
    public function run(array $validated): void
    {
        $email = mb_strtolower($validated['email']);
        $user = $this->find($email);

        if ($user === null) {
            try {
                $created = $this->createClientAccount->run($validated, requireInvitation: false);
            } catch (UniqueConstraintViolationException) {
                $created = null;
            }

            if ($created !== null) {
                // Outside the transaction so the queued verification mail never points at a rolled-back user.
                event(new Registered($created));

                return;
            }

            // A concurrent signup took the address first: answer as for an existing one.
            $user = $this->find($email);

            if ($user === null) {
                return;
            }
        }

        $this->handleExisting($user, $validated, $email);
    }

    /**
     * @param  array{name: string, email: string, password: string, invite?: string|null, timezone: string}  $validated
     */
    private function handleExisting(User $user, array $validated, string $email): void
    {
        if (! $user->is_admin && $user->isActive() && ! $user->hasVerifiedEmail()) {
            // The "hashed" cast hashes the plain password on save.
            $user->forceFill([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'timezone' => $validated['timezone'],
            ])->save();

            $this->mail($email, fn () => $user->sendEmailVerificationNotification());

            return;
        }

        // Same hashing work as the other branches; the result is discarded.
        Hash::make($validated['password']);

        if ($user->is_admin || $user->isActive()) {
            $this->mail($email, fn () => $user->notify(new RegistrationAttempted));
        }
    }

    private function find(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [$email])->first();
    }

    private function mail(string $email, Closure $send): void
    {
        RateLimiter::attempt('register-mail:'.sha1($email), self::MAIL_ATTEMPTS, $send, self::MAIL_DECAY_SECONDS);
    }
}
