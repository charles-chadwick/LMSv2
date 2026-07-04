<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('loads nested replies to arbitrary depth on the thread show page', function () {
    $course = Course::factory()->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->create();

    $topLevel = DiscussionReply::factory()->for($discussion)->create(['parent_id' => null]);
    $child = DiscussionReply::factory()->for($discussion)->create(['parent_id' => $topLevel->id]);
    $grandchild = DiscussionReply::factory()->for($discussion)->create([
        'parent_id' => $child->id,
        'body' => 'grandchild reply body',
    ]);

    $this->actingAs($student)
        ->get(route('discussions.show', $discussion))
        ->assertInertia(fn (Assert $page) => $page->component('Discussions/Show')
            ->where('discussion.replies.0.children.0.children.0.body', $grandchild->body));
});
