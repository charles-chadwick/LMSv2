<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Determine whether the user may drop the enrollment (self-drop or instructor removal).
     */
    public function drop(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            return false;
        }

        return $enrollment->user_id === $user->id
            || $enrollment->course->instructor_id === $user->id;
    }
}
