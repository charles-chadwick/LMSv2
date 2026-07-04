<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user may edit the given profile.
     *
     * A user may only manage their own profile (admins are granted access
     * globally via Gate::before).
     */
    public function update(User $user, User $model): bool
    {
        return $user->is($model);
    }
}
