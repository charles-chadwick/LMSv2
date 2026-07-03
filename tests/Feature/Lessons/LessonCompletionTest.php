<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an enrolled user can mark a lesson complete and progress updates', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lesson_a = Lesson::factory()->for($module)->create(['position' => 0]);
    Lesson::factory()->for($module)->create(['position' => 1]);
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson_a]))->assertRedirect();

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson_a->id])->exists())->toBeTrue()
        ->and($enrollment->fresh()->progress_percentage)->toBe(50);
});

test('marking a lesson complete twice is idempotent', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]));
    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]));

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id])->count())->toBe(1)
        ->and($enrollment->fresh()->progress_percentage)->toBe(100);
});

test('a previewing instructor cannot mark a lesson complete', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->post(route('lessons.complete', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('an unrelated user cannot mark a lesson complete', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]))->assertForbidden();
});

test('marking a lesson that belongs to another course 404s', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);
    $other_course = Course::factory()->published()->create();
    $other_module = Module::factory()->for($other_course)->create();
    $foreign_lesson = Lesson::factory()->for($other_module)->create();

    $this->actingAs($user)->post(route('lessons.complete', [$course, $foreign_lesson]))->assertNotFound();
});

test('a dropped student cannot mark a lesson complete', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    Enrollment::factory()->for($user, 'student')->for($course)->dropped()->create();

    $this->actingAs($user)->post(route('lessons.complete', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('a guest cannot mark a lesson complete', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->post(route('lessons.complete', [$course, $lesson]))->assertRedirect(route('login'));
});
