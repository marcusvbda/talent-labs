<?php

namespace App\Actions\Users;

use App\Enums\Region;
use App\Enums\UserStatus;
use App\Invitations\InvitationTokens;
use App\Models\Invitation;
use App\Models\User;
use App\Plans\PlanCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates an active, unverified client account from the signup form, applying and
 * consuming a usable invitation when one is given. Does not fire `Registered`: the
 * caller does that after the transaction so the queued verification mail never
 * points at a rolled-back user.
 */
final class CreateClientAccount
{
    /**
     * @param  array{name: string, email: string, password: string, invite?: string|null, timezone: string}  $validated
     * @return User|null null when an invitation is required and the given one is unusable
     *
     * @throws UniqueConstraintViolationException when the email was taken concurrently
     */
    public function run(array $validated, bool $requireInvitation): ?User
    {
        $invite = $validated['invite'] ?? null;

        // The row lock serialises concurrent submits of the same link: the
        // loser re-reads the invitation as used and, when an invitation is
        // required, gets null; otherwise it registers without the invitation.
        return DB::transaction(function () use ($validated, $invite, $requireInvitation): ?User {
            $invitation = null;

            if ($invite !== null && $invite !== '') {
                $invitation = Invitation::query()
                    ->where('token_hash', InvitationTokens::hash($invite))
                    ->lockForUpdate()
                    ->first();
            }

            if ($invitation !== null && ! $invitation->isUsable()) {
                $invitation = null;
            }

            if ($invitation === null && $requireInvitation) {
                return null;
            }

            $user = new User;
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_admin' => false,
                'status' => UserStatus::Active,
                // Never from the request: the invitation's plan or the configured default.
                'plan_key' => $invitation?->planKeyOrDefault()
                    ?? app(PlanCatalog::class)->get((string) config('talent.plans.default'))->key,
                'locale' => app()->getLocale(),
                'timezone' => $validated['timezone'],
                'country' => null,
                'region' => Region::Row,
            ])->save();

            $invitation?->forceFill(['used_by' => $user->id, 'used_at' => now()])->save();

            return $user;
        });
    }
}
