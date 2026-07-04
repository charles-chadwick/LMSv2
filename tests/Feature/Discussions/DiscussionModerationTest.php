<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets the author edit and delete their own discussion', function () {
    $course = Course::factory()->create();
    $author = User::factory()->student()->create();
    Enrollment::factory()->for($author, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->for($author, 'author')->create();

    $this->actingAs($author)->patch(route('discussions.update', $discussion), ['title' => 'Edited', 'body' => 'New body'])->assertRedirect();
    expect($discussion->fresh()->title)->toBe('Edited');

    $this->actingAs($author)->delete(route('discussions.destroy', $discussion))->assertRedirect();
    expect($discussion->fresh()->trashed())->toBeTrue();
});

it('lets the instructor pin, lock, and delete any discussion but forbids the author from pinning', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $author = User::factory()->student()->create();
    Enrollment::factory()->for($author, 'student')->for($course)->create();
    $discussion = Discussion::factory()->for($course)->for($author, 'author')->create();

    $this->actingAs($instructor)->post(route('discussions.pin', $discussion))->assertRedirect();
    expect($discussion->fresh()->is_pinned)->toBeTrue();

    $this->actingAs($instructor)->post(route('discussions.lock', $discussion))->assertRedirect();
    expect($discussion->fresh()->is_locked)->toBeTrue();

    $this->actingAs($author)->post(route('discussions.pin', $discussion))->assertForbidden();
});
