<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;

class AdminGuard
{
    /**
     * Determine whether a change to a user's admin/active status is forbidden.
     *
     * @param  'block'|'demote'|'delete'  $change
     * @return string|null A user-facing reason the change is forbidden, or null if it's allowed.
     */
    public function violation(User $actor, User $target, string $change): ?string
    {
        if ($actor->is($target)) {
            return match ($change) {
                'block' => "You can't block yourself.",
                'demote' => "You can't remove your own admin access.",
                'delete' => "You can't delete yourself.",
            };
        }

        $targetIsActiveAdmin = $target->is_admin && $target->status === UserStatus::Active;

        if (! $targetIsActiveAdmin) {
            return null;
        }

        $hasOtherActiveAdmin = User::where('is_admin', true)
            ->where('status', UserStatus::Active)
            ->where('id', '!=', $target->id)
            ->exists();

        if ($hasOtherActiveAdmin) {
            return null;
        }

        return match ($change) {
            'block' => "The last active admin can't be blocked.",
            'demote' => "The last active admin can't lose admin access.",
            'delete' => "The last active admin can't be deleted.",
        };
    }
}
