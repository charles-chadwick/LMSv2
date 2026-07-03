<?php

namespace App\Policies;

use App\Enums\CourseStatus;
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

    public function enroll(User $user, Course $course): bool
    {
        return $course->status === CourseStatus::Published;
    }
}
