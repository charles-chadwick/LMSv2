<?php

use App\Events\DiscussionReplyPosted;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('broadcasts a posted reply on its discussion private channel with a shaped payload', function () {
    $reply = DiscussionReply::factory()->create();

    $event = new DiscussionReplyPosted($reply);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe('private-discussions.'.$reply->discussion_id)
        ->and($event->broadcastWith())->toMatchArray([
            'id' => $reply->id,
            'discussion_id' => $reply->discussion_id,
            'body' => $reply->body,
        ]);
    expect($event->broadcastWith()['author'])->toHaveKey('id');
});

it('authorizes the discussion channel for a course member and denies an outsider', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $member = User::factory()->student()->create();
    Enrollment::factory()->for($member, 'student')->for($course)->create();
    $outsider = User::factory()->student()->create();
    $discussion = Discussion::factory()->for($course)->create();

    $this->actingAs($member)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-discussions.'.$discussion->id,
    ])->assertOk();

    $this->actingAs($outsider)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-discussions.'.$discussion->id,
    ])->assertForbidden();
});
