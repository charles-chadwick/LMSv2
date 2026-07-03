<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function ownedModule(User $instructor): Module
{
    $course = Course::factory()->for($instructor, 'instructor')->create();

    return Module::factory()->for($course)->create();
}

test('the owner can create a lesson with a unique slug appended at the end', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    Lesson::factory()->for($module)->create(['position' => 0]);

    $this->actingAs($instructor)
        ->post(route('lessons.store', $module), [
            'title' => 'Intro Lesson',
            'content' => '<p>Hello</p>',
            'duration_minutes' => 10,
        ])
        ->assertRedirect();

    $lesson = Lesson::where('title', 'Intro Lesson')->sole();
    expect($lesson->slug)->toBe('intro-lesson')
        ->and($lesson->position)->toBe(1)
        ->and($lesson->module_id)->toBe($module->id);
});

test('a duplicate lesson title gets a globally unique slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    Lesson::factory()->for($module)->create(['title' => 'Welcome', 'slug' => 'welcome']);

    $this->actingAs($instructor)->post(route('lessons.store', $module), [
        'title' => 'Welcome',
    ])->assertRedirect();

    expect(Lesson::where('slug', 'welcome-2')->exists())->toBeTrue();
});

test('the owner can update a lesson without changing its slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    $lesson = Lesson::factory()->for($module)->create(['title' => 'Original', 'slug' => 'original']);

    $this->actingAs($instructor)
        ->put(route('lessons.update', $lesson), ['title' => 'Renamed', 'content' => '<p>New</p>'])
        ->assertRedirect();

    $lesson->refresh();
    expect($lesson->title)->toBe('Renamed')->and($lesson->slug)->toBe('original');
});

test('the owner can soft-delete a lesson', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)->delete(route('lessons.destroy', $lesson))->assertRedirect();

    expect($lesson->fresh()->trashed())->toBeTrue();
});

test('the owner can reorder lessons', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = ownedModule($instructor);
    $a = Lesson::factory()->for($module)->create(['position' => 0]);
    $b = Lesson::factory()->for($module)->create(['position' => 1]);

    $this->actingAs($instructor)
        ->post(route('lessons.reorder', $module), ['lessons' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->fresh()->position)->toBe(0)->and($a->fresh()->position)->toBe(1);
});

test('a non-owner cannot create a lesson', function (): void {
    $instructor = User::factory()->instructor()->create();
    $module = Module::factory()->create();

    $this->actingAs($instructor)
        ->post(route('lessons.store', $module), ['title' => 'X'])
        ->assertForbidden();
});

test('a guest is redirected from lesson creation', function (): void {
    $module = Module::factory()->create();

    $this->post(route('lessons.store', $module), ['title' => 'X'])->assertRedirect(route('login'));
});
