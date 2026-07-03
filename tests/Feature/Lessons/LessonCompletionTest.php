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

test('viewing a lesson marks it complete and updates progress', function (): void {
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

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson_a]))->assertOk();

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson_a->id])->exists())->toBeTrue()
        ->and($enrollment->fresh()->progress_percentage)->toBe(50);
});

test('viewing a lesson twice is idempotent', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]));
    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]));

    expect(LessonCompletion::where(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id])->count())->toBe(1)
        ->and($enrollment->fresh()->progress_percentage)->toBe(100);
});

test('viewing every lesson drives progress to 100 percent', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lesson_a = Lesson::factory()->for($module)->create(['position' => 0]);
    $lesson_b = Lesson::factory()->for($module)->create(['position' => 1]);
    $enrollment = $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson_a]));
    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson_b]));

    expect($enrollment->fresh()->progress_percentage)->toBe(100);
});

test('a previewing instructor does not generate a completion', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->get(route('lessons.show', [$course, $lesson]))->assertOk();

    expect(LessonCompletion::count())->toBe(0);
});

test('an unrelated user cannot view a lesson and creates no completion', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('a dropped student cannot view a lesson and creates no completion', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    Enrollment::factory()->for($user, 'student')->for($course)->dropped()->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();

    expect(LessonCompletion::count())->toBe(0);
});

test('viewing a lesson that belongs to another course 404s', function (): void {
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

    $this->actingAs($user)->get(route('lessons.show', [$course, $foreign_lesson]))->assertNotFound();
});

test('a guest is redirected to login', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->get(route('lessons.show', [$course, $lesson]))->assertRedirect(route('login'));
});
