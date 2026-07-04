<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('renders a discussion thread and its replies without JS errors', function () {
    $course = Course::factory()->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->create(['title' => 'Live Q']);
    DiscussionReply::factory()->for($discussion)->create(['body' => 'An existing answer']);
    $this->actingAs($student);

    $page = visit(route('discussions.show', $discussion));

    // Mounting the page subscribes to the discussions.{id} Echo channel; a failed WS
    // connection to a down Reverb server is handled by pusher-js and is NOT a JS error.
    $page->assertSee('Live Q')
        ->assertSee('An existing answer')
        ->assertNoJavaScriptErrors();
});
