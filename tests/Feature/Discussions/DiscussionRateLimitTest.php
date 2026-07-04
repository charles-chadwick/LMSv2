<?php

use App\Events\DiscussionReplyPosted;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('rate limits discussion reply posting to 30 per minute per user', function () {
    Event::fake([DiscussionReplyPosted::class]);
    Notification::fake();

    $course = Course::factory()->create();
    $member = User::factory()->student()->create();
    Enrollment::factory()->for($member, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->create();

    for ($i = 1; $i <= 30; $i++) {
        $this->actingAs($member)
            ->post(route('discussion-replies.store', $discussion), ['body' => "r$i"])
            ->assertRedirect();
    }

    $this->actingAs($member)
        ->post(route('discussion-replies.store', $discussion), ['body' => 'r31'])
        ->assertStatus(429);
});
