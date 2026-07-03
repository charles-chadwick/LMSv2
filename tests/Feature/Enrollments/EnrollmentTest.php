<?php

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the enroll ability allows any user on a published course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    expect($user->can('enroll', $course))->toBeTrue();
});

test('the enroll ability denies a draft course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Draft]);

    expect($user->can('enroll', $course))->toBeFalse();
});

test('a student can enroll in a published course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertRedirect();

    $enrollment = Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->sole();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrolled_at)->not->toBeNull();
});

test('an instructor can self-enroll in a published course', function (): void {
    $user = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertRedirect();

    expect(Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->exists())->toBeTrue();
});

test('an admin can self-enroll in a published course', function (): void {
    $user = User::factory()->admin()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertRedirect();

    expect(Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->exists())->toBeTrue();
});

test('enrolling twice does not create a duplicate enrollment', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($user)->post(route('courses.enroll', $course));
    $this->actingAs($user)->post(route('courses.enroll', $course));

    expect(Enrollment::where(['user_id' => $user->id, 'course_id' => $course->id])->count())->toBe(1);
});

test('a user cannot enroll in a draft course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Draft]);

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertForbidden();

    expect(Enrollment::where('course_id', $course->id)->exists())->toBeFalse();
});

test('a user cannot enroll in an archived course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Archived]);

    $this->actingAs($user)->post(route('courses.enroll', $course))->assertForbidden();
});

test('a guest cannot enroll', function (): void {
    $course = Course::factory()->published()->create();

    $this->post(route('courses.enroll', $course))->assertRedirect(route('login'));
});
