<?php

namespace App\Policies;

use App\Models\DiscussionReply;
use App\Models\User;

class DiscussionReplyPolicy
{
    public function update(User $user, DiscussionReply $reply): bool
    {
        return $reply->user_id === $user->id;
    }

    public function delete(User $user, DiscussionReply $reply): bool
    {
        return $reply->user_id === $user->id
            || $reply->discussion->course->instructor_id === $user->id;
    }
}
