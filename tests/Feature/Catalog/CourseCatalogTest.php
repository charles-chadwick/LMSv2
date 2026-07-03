<?php

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the catalog lists only published courses', function (): void {
    $user = User::factory()->student()->create();
    Course::factory()->published()->create(['title' => 'Published One']);
    Course::factory()->create(['status' => CourseStatus::Draft, 'title' => 'Draft One']);
    Course::factory()->create(['status' => CourseStatus::Archived, 'title' => 'Archived One']);

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Index')
            ->has('courses', 1)
            ->where('courses.0.title', 'Published One')
        );
});

test('the catalog marks courses the user is already enrolled in', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('catalog.index'))
        ->assertInertia(fn ($page) => $page->where('courses.0.is_enrolled', true));
});

test('a guest is redirected to login from the catalog', function (): void {
    $this->get(route('catalog.index'))->assertRedirect(route('login'));
});

test('the course detail renders the syllabus in position order', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module_one = Module::factory()->for($course)->create(['title' => 'Module A', 'position' => 0]);
    $module_two = Module::factory()->for($course)->create(['title' => 'Module B', 'position' => 1]);
    Lesson::factory()->for($module_one)->create(['title' => 'Lesson A1', 'position' => 0]);
    Lesson::factory()->for($module_two)->create(['title' => 'Lesson B1', 'position' => 0]);

    $this->actingAs($user)
        ->get(route('catalog.show', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Show')
            ->where('course.modules.0.title', 'Module A')
            ->where('course.modules.1.title', 'Module B')
            ->where('course.modules.0.lessons.0.title', 'Lesson A1')
            ->where('is_enrolled', false)
        );
});

test('the course detail 404s for a draft course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Draft]);

    $this->actingAs($user)->get(route('catalog.show', $course))->assertNotFound();
});

test('the course detail 404s for an archived course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create(['status' => CourseStatus::Archived]);

    $this->actingAs($user)->get(route('catalog.show', $course))->assertNotFound();
});

test('the course detail reflects enrollment state after enrolling', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('catalog.show', $course))
        ->assertInertia(fn ($page) => $page->where('is_enrolled', true));
});
