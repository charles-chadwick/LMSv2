<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Discussion;
use App\Models\User;

class DiscussionPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return $user->can('learn', $course);
    }

    public function view(User $user, Discussion $discussion): bool
    {
        return $user->can('learn', $discussion->course);
    }

    public function create(User $user, Course $course): bool
    {
        return $user->can('learn', $course);
    }

    public function reply(User $user, Discussion $discussion): bool
    {
        return ! $discussion->is_locked && $user->can('learn', $discussion->course);
    }

    public function update(User $user, Discussion $discussion): bool
    {
        return $discussion->user_id === $user->id;
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $discussion->user_id === $user->id
            || $discussion->course->instructor_id === $user->id;
    }

    public function pin(User $user, Discussion $discussion): bool
    {
        return $discussion->course->instructor_id === $user->id;
    }

    public function lock(User $user, Discussion $discussion): bool
    {
        return $discussion->course->instructor_id === $user->id;
    }
}
