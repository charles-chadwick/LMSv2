<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an enrolled user can learn a course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    expect($user->can('learn', $course))->toBeTrue();
});

test('the course instructor can learn without enrolling', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    expect($instructor->can('learn', $course))->toBeTrue();
});

test('an admin can learn any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('learn', $course))->toBeTrue();
});

test('an unrelated user cannot learn a course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    expect($user->can('learn', $course))->toBeFalse();
});

test('a dropped student cannot learn a course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Dropped,
        'enrolled_at' => now(),
    ]);

    expect($user->can('learn', $course))->toBeFalse();
});

test('a completed student can still learn a course for review', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Completed,
        'enrolled_at' => now(),
    ]);

    expect($user->can('learn', $course))->toBeTrue();
});
