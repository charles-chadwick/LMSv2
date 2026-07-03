<?php

use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owning instructor can manage content', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    expect($instructor->can('manageContent', $course))->toBeTrue();
});

test('a non-owner instructor cannot manage content', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    expect($instructor->can('manageContent', $course))->toBeFalse();
});

test('a student cannot manage content', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->create();

    expect($student->can('manageContent', $course))->toBeFalse();
});

test('an admin can manage content on any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create();

    expect($admin->can('manageContent', $course))->toBeTrue();
});
