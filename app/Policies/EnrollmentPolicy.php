<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Determine whether the user may drop the enrollment.
     *
     * Dropping is a staff action: an instructor may remove students from their
     * own courses, and admins from any course (via Gate::before). Students can
     * no longer drop themselves.
     */
    public function drop(User $user, Enrollment $enrollment): bool
    {
        if (! $enrollment->status->isDroppable()) {
            return false;
        }

        return $user->can('enroll students') && $enrollment->course->instructor_id === $user->id;
    }
}
