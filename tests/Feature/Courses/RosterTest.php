<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an instructor can view the roster of their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $enrollment = Enrollment::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Courses/Roster')
            ->has('students', 1)
            ->where('students.0.id', $enrollment->id)
        );
});

test('an instructor cannot view the roster of another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->published()->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $otherCourse))
        ->assertForbidden();
});

test('a student cannot view a course roster', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->get(route('courses.roster', $course))
        ->assertForbidden();
});
