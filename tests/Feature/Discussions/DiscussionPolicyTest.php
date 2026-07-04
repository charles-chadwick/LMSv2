<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an enrolled student and the instructor view and create, but not an outsider', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();
    $outsider = User::factory()->student()->create();
    $discussion = Discussion::factory()->for($course)->create();

    expect($student->can('view', $discussion))->toBeTrue()
        ->and($instructor->can('view', $discussion))->toBeTrue()
        ->and($outsider->can('view', $discussion))->toBeFalse()
        ->and($student->can('create', [Discussion::class, $course]))->toBeTrue();
});

it('blocks replies on a locked discussion but allows the author to edit and the instructor to moderate', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $author = User::factory()->student()->create();
    Enrollment::factory()->for($author, 'student')->for($course)->create();
    $open = Discussion::factory()->for($course)->for($author, 'author')->create();
    $locked = Discussion::factory()->for($course)->for($author, 'author')->locked()->create();

    expect($author->can('reply', $open))->toBeTrue()
        ->and($author->can('reply', $locked))->toBeFalse()
        ->and($author->can('update', $open))->toBeTrue()
        ->and($instructor->can('update', $open))->toBeFalse()
        ->and($instructor->can('delete', $open))->toBeTrue()
        ->and($instructor->can('lock', $open))->toBeTrue()
        ->and($author->can('lock', $open))->toBeFalse();
});
