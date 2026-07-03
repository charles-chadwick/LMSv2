<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owner can create a module appended at the end', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    Module::factory()->for($course)->create(['position' => 0]);

    $this->actingAs($instructor)
        ->post(route('modules.store', $course), ['title' => 'New Module', 'description' => 'Desc'])
        ->assertRedirect();

    $module = Module::where('title', 'New Module')->sole();
    expect($module->course_id)->toBe($course->id)
        ->and($module->position)->toBe(1);
});

test('the owner can update a module', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create(['title' => 'Old']);

    $this->actingAs($instructor)
        ->put(route('modules.update', $module), ['title' => 'Renamed', 'description' => null])
        ->assertRedirect();

    expect($module->fresh()->title)->toBe('Renamed');
});

test('the owner can soft-delete a module', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();

    $this->actingAs($instructor)->delete(route('modules.destroy', $module))->assertRedirect();

    expect($module->fresh()->trashed())->toBeTrue();
});

test('deleting a module soft-deletes its lessons', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->delete(route('modules.destroy', $module))->assertRedirect();

    expect($module->fresh()->trashed())->toBeTrue()
        ->and($lesson->fresh()->trashed())->toBeTrue();
});

test('the owner can reorder modules', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $a = Module::factory()->for($course)->create(['position' => 0]);
    $b = Module::factory()->for($course)->create(['position' => 1]);

    $this->actingAs($instructor)
        ->post(route('modules.reorder', $course), ['modules' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->fresh()->position)->toBe(0)->and($a->fresh()->position)->toBe(1);
});

test('a non-owner cannot create a module', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)
        ->post(route('modules.store', $course), ['title' => 'X'])
        ->assertForbidden();
});

test('a guest is redirected from module creation', function (): void {
    $course = Course::factory()->create();

    $this->post(route('modules.store', $course), ['title' => 'X'])->assertRedirect(route('login'));
});

test('creating a module requires a title', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    $this->actingAs($instructor)
        ->post(route('modules.store', $course), ['title' => ''])
        ->assertSessionHasErrors('title');
});
