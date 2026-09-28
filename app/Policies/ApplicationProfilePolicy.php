<?php

namespace App\Policies;

use App\Models\ApplicationProfile;
use App\Models\User;

class ApplicationProfilePolicy
{
    public function view(User $user, ApplicationProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }

    public function update(User $user, ApplicationProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }

    public function delete(User $user, ApplicationProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }
}
