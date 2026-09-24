<?php

namespace App\Policies;

use App\Models\JobPreference;
use App\Models\User;

class JobPreferencePolicy
{
    public function view(User $user, JobPreference $preference): bool
    {
        return $user->id === $preference->user_id;
    }

    public function update(User $user, JobPreference $preference): bool
    {
        return $user->id === $preference->user_id;
    }
}
