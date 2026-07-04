<?php

use App\Events\DiscussionReplyPosted;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\NewDiscussionReply;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function memberOf(Course $course): User
{
    $user = User::factory()->student()->create();
    Enrollment::factory()->for($user, 'student')->for($course)->create();

    return $user;
}

it('posts a reply, broadcasts it, and notifies the question author and instructor but not the actor', function () {
    Event::fake([DiscussionReplyPosted::class]);
    Notification::fake();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $asker = memberOf($course);
    $replier = memberOf($course);
    $discussion = Discussion::factory()->for($course)->for($asker, 'author')->create();

    $this->actingAs($replier)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'Here is an answer'])
        ->assertRedirect();

    Event::assertDispatched(DiscussionReplyPosted::class);
    Notification::assertSentTo($asker, NewDiscussionReply::class);
    Notification::assertSentTo($instructor, NewDiscussionReply::class);
    Notification::assertNotSentTo($replier, NewDiscussionReply::class);
});

it('also notifies the parent reply author on a nested reply', function () {
    Event::fake([DiscussionReplyPosted::class]);
    Notification::fake();
    $course = Course::factory()->create();
    $asker = memberOf($course);
    $parentAuthor = memberOf($course);
    $replier = memberOf($course);
    $discussion = Discussion::factory()->for($course)->for($asker, 'author')->create();
    $parent = DiscussionReply::factory()->for($discussion)->for($parentAuthor, 'author')->create();

    $this->actingAs($replier)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'reply', 'parent_id' => $parent->id])
        ->assertRedirect();

    Notification::assertSentTo($parentAuthor, NewDiscussionReply::class);
});

it('forbids replying to a locked discussion', function () {
    $course = Course::factory()->create();
    $member = memberOf($course);
    $discussion = Discussion::factory()->for($course)->locked()->create();

    $this->actingAs($member)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'nope'])
        ->assertForbidden();
});

it('rejects a parent_id from another discussion', function () {
    $course = Course::factory()->create();
    $member = memberOf($course);
    $discussion = Discussion::factory()->for($course)->create();
    $foreignParent = DiscussionReply::factory()->create();

    $this->actingAs($member)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'x', 'parent_id' => $foreignParent->id])
        ->assertSessionHasErrors('parent_id');
});

it('lets the author edit and the instructor delete a reply', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $author = memberOf($course);
    $discussion = Discussion::factory()->for($course)->create();
    $reply = DiscussionReply::factory()->for($discussion)->for($author, 'author')->create();

    $this->actingAs($author)->patch(route('discussion-replies.update', $reply), ['body' => 'edited'])->assertRedirect();
    expect($reply->fresh()->body)->toBe('edited');

    $this->actingAs($instructor)->delete(route('discussion-replies.destroy', $reply))->assertRedirect();
    expect($reply->fresh()->trashed())->toBeTrue();
});
