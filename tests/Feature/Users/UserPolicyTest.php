<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets admins manage anyone and view the list', function () {
    $admin = User::factory()->admin()->create();
    $someone = User::factory()->student()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('manage', $someone))->toBeTrue();
});

it('lets instructors manage only the students they created', function () {
    $instructor = User::factory()->instructor()->create();
    $ownStudent = User::factory()->student()->create(['created_by' => $instructor->id]);
    $otherStudent = User::factory()->student()->create();
    $otherInstructor = User::factory()->instructor()->create(['created_by' => $instructor->id]);

    expect($instructor->can('viewAny', User::class))->toBeTrue();
    expect($instructor->can('create', User::class))->toBeTrue();
    expect($instructor->can('manage', $ownStudent))->toBeTrue();
    expect($instructor->can('manage', $otherStudent))->toBeFalse();
    expect($instructor->can('manage', $otherInstructor))->toBeFalse();
    expect($instructor->can('delete', $ownStudent))->toBeTrue();
});

it('denies students any management ability', function () {
    $student = User::factory()->student()->create();

    expect($student->can('viewAny', User::class))->toBeFalse();
    expect($student->can('create', User::class))->toBeFalse();
});
