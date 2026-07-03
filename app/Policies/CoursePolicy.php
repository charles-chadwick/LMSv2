<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('create courses');
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can('create courses') || $course->instructor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create courses');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('update courses') && $course->instructor_id === $user->id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can('delete courses') && $course->instructor_id === $user->id;
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can('publish courses') && $course->instructor_id === $user->id;
    }

    public function archive(User $user, Course $course): bool
    {
        return $user->can('publish courses') && $course->instructor_id === $user->id;
    }

    /**
     * Determine whether the user may enroll students into the course.
     *
     * Enrollment is a staff action: an instructor may enroll students into
     * their own courses, and admins into any course (via Gate::before).
     */
    public function enrollStudents(User $user, Course $course): bool
    {
        return $user->can('enroll students') && $course->instructor_id === $user->id;
    }

    public function learn(User $user, Course $course): bool
    {
        return $user->enrollments()
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->exists()
            || $course->instructor_id === $user->id;
    }

    public function manageContent(User $user, Course $course): bool
    {
        return $user->can('manage course content') && $course->instructor_id === $user->id;
    }

    public function viewRoster(User $user, Course $course): bool
    {
        return $user->can('update courses') && $course->instructor_id === $user->id;
    }
}
