<?php

use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an instructor can update their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    expect($instructor->can('update', $course))->toBeTrue();
});

test('an instructor cannot update another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $other = User::factory()->instructor()->create();
    $course = Course::factory()->for($other, 'instructor')->create();

    expect($instructor->can('update', $course))->toBeFalse();
});

test('a student cannot create courses', function (): void {
    $student = User::factory()->student()->create();

    expect($student->can('create', Course::class))->toBeFalse();
});

test('an admin can update any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('update', $course))->toBeTrue();
});

test('an admin can publish any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('publish', $course))->toBeTrue();
});

test('instructors have the create_courses ability shared to inertia', function (): void {
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.user.can.create_courses', true));
});

test('students do not have the create_courses ability', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.user.can.create_courses', false));
});
