<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Application $application): bool
    {
        return $user->id === $application->user_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Application $application): bool
    {
        return false;
    }

    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
