<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user may view the management user list.
     *
     * Admins pass via Gate::before; instructors get a list scoped to their
     * own students in the controller query.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Instructor->value);
    }

    /**
     * Determine whether the user may provision new accounts.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Instructor->value);
    }

    /**
     * Determine whether the user may edit/administer the target account.
     *
     * Instructors may only manage students they personally created.
     */
    public function manage(User $user, User $target): bool
    {
        return $target->hasRole(UserRole::Student->value)
            && (int) $target->created_by === $user->id;
    }

    /**
     * Determine whether the user may remove the target account.
     *
     * The "cannot delete yourself" rule is also enforced in the controller so
     * it applies to admins (who bypass this policy via Gate::before).
     */
    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target) && $this->manage($user, $target);
    }

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
