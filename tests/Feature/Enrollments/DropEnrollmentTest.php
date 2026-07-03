<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('a student can drop their own active enrollment and keep progress', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($student, 'student')->for($course)->create([
        'status' => EnrollmentStatus::Active,
        'progress_percentage' => 30,
    ]);

    $this->actingAs($student)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertRedirect();

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Dropped)
        ->and($enrollment->progress_percentage)->toBe(30);
});

test('an instructor can remove an active student from their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $enrollment = Enrollment::factory()->for($course)->create(['status' => EnrollmentStatus::Active]);

    $this->actingAs($instructor)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertRedirect();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Dropped);
});

test('an instructor cannot remove a student from another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($otherCourse)->create(['status' => EnrollmentStatus::Active]);

    $this->actingAs($instructor)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertForbidden();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Active);
});

test('a student cannot drop another users enrollment', function (): void {
    $student = User::factory()->student()->create();
    $enrollment = Enrollment::factory()->create(['status' => EnrollmentStatus::Active]);

    $this->actingAs($student)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertForbidden();
});

test('a completed enrollment cannot be dropped', function (): void {
    $student = User::factory()->student()->create();
    $enrollment = Enrollment::factory()->for($student, 'student')->completed()->create();

    $this->actingAs($student)
        ->delete(route('enrollments.destroy', $enrollment))
        ->assertForbidden();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Completed);
});

test('re-enrolling after a drop reactivates the same row with progress intact', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($student, 'student')->for($course)->create([
        'status' => EnrollmentStatus::Active,
        'progress_percentage' => 50,
    ]);

    $this->actingAs($student)->delete(route('enrollments.destroy', $enrollment));
    $this->actingAs($student)->post(route('courses.enroll', $course))->assertRedirect();

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->progress_percentage)->toBe(50)
        ->and(Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->count())->toBe(1);
});

test('a guest cannot drop an enrollment', function (): void {
    $enrollment = Enrollment::factory()->create(['status' => EnrollmentStatus::Active]);

    $this->delete(route('enrollments.destroy', $enrollment))->assertRedirect(route('login'));
});
