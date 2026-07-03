<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the catalog detail exposes the enrollment id and status for an enrolled student', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($student, 'student')->for($course)->create([
        'status' => EnrollmentStatus::Active,
    ]);

    $this->actingAs($student)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page
            ->where('enrollment_id', $enrollment->id)
            ->where('enrollment_status', 'Active')
        );
});

test('the catalog detail exposes null enrollment props for a non-enrolled student', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page
            ->where('enrollment_id', null)
            ->where('enrollment_status', null)
        );
});
