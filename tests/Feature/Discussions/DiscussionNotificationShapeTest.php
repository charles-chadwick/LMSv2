<?php

use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use App\Notifications\NewDiscussionReply;

it('shapes the new-question notification for database + broadcast', function () {
    $discussion = Discussion::factory()->create();
    $notification = new NewDiscussionQuestion($discussion);
    $data = $notification->toArray(new User);

    expect($notification->via(new User))->toBe(['database', 'broadcast'])
        ->and($data)->toHaveKeys(['discussion_id', 'course_slug', 'type', 'actor_name', 'excerpt'])
        ->and($data['type'])->toBe('new_question')
        ->and($data['discussion_id'])->toBe($discussion->id);
});

it('shapes the new-reply notification', function () {
    $reply = DiscussionReply::factory()->create();
    $notification = new NewDiscussionReply($reply);
    $data = $notification->toArray(new User);

    expect($data['type'])->toBe('new_reply')
        ->and($data['discussion_id'])->toBe($reply->discussion_id);
});
