<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an enrolled user can view a lesson', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create(['title' => 'Intro']);
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('lessons.show', [$course, $lesson]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lessons/Show')
            ->where('lesson.title', 'Intro')
            ->where('is_complete', true)
            ->where('can_complete', true)
        );
});

test('prev and next are computed across module boundaries', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module_one = Module::factory()->for($course)->create(['position' => 0]);
    $module_two = Module::factory()->for($course)->create(['position' => 1]);
    $lesson_a = Lesson::factory()->for($module_one)->create(['position' => 0]);
    $lesson_b = Lesson::factory()->for($module_two)->create(['position' => 0]);
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('lessons.show', [$course, $lesson_a]))
        ->assertInertia(fn ($page) => $page
            ->where('prev', null)
            ->where('next.slug', $lesson_b->slug)
        );

    $this->actingAs($user)
        ->get(route('lessons.show', [$course, $lesson_b]))
        ->assertInertia(fn ($page) => $page
            ->where('prev.slug', $lesson_a->slug)
            ->where('next', null)
        );
});

test('a lesson from another course 404s under this course', function (): void {
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

test('a non-enrolled user cannot view a lesson', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();
});

test('an instructor can preview a lesson without enrolling', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)
        ->get(route('lessons.show', [$course, $lesson]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can_complete', false));
});

test('a dropped student cannot view a lesson', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    Enrollment::factory()->for($user, 'student')->for($course)->dropped()->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertForbidden();
});

test('a completed student can still view a lesson for review', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    Enrollment::factory()->for($user, 'student')->for($course)->completed()->create();

    $this->actingAs($user)->get(route('lessons.show', [$course, $lesson]))->assertOk();
});

test('a guest is redirected to login from a lesson', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->get(route('lessons.show', [$course, $lesson]))->assertRedirect(route('login'));
});
