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

it('bounds the reply tree to the maximum reply depth to prevent unbounded recursion', function () {
    $maxReplyDepth = 20;

    $course = Course::factory()->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->create();

    $parent = DiscussionReply::factory()->for($discussion)->create(['parent_id' => null]);
    for ($i = 0; $i < $maxReplyDepth + 3; $i++) {
        $parent = DiscussionReply::factory()->for($discussion)->create(['parent_id' => $parent->id]);
    }

    $props = null;

    $this->actingAs($student)
        ->get(route('discussions.show', $discussion))
        ->assertInertia(function (Assert $page) use (&$props) {
            $page->component('Discussions/Show');
            $props = $page->toArray();
        });

    $node = $props['props']['discussion']['replies'][0];
    $depth = 0;
    while (! empty($node['children'])) {
        $depth++;
        $node = $node['children'][0];
    }

    expect($depth)->toBe($maxReplyDepth);
});
