<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owner sees the curriculum builder with modules and lessons in order', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module_one = Module::factory()->for($course)->create(['title' => 'Module A', 'position' => 0]);
    Module::factory()->for($course)->create(['title' => 'Module B', 'position' => 1]);
    Lesson::factory()->for($module_one)->create(['title' => 'Lesson A1', 'position' => 0]);

    $this->actingAs($instructor)
        ->get(route('curriculum.show', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Curriculum/Show')
            ->where('modules.0.title', 'Module A')
            ->where('modules.1.title', 'Module B')
            ->where('modules.0.lessons.0.title', 'Lesson A1')
        );
});

test('a non-owner cannot open the curriculum builder', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)->get(route('curriculum.show', $course))->assertForbidden();
});

test('a guest is redirected from the curriculum builder', function (): void {
    $course = Course::factory()->create();

    $this->get(route('curriculum.show', $course))->assertRedirect(route('login'));
});
