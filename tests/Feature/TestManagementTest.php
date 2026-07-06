<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Test;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owner can create a test for a course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    $this->actingAs($instructor)
        ->post(route('tests.store', $course), [
            'title' => 'Final Exam',
            'max_attempts' => 2,
            'passing_score' => 70,
        ])
        ->assertRedirect(route('tests.index', $course->slug));

    $test = Test::where('title', 'Final Exam')->sole();
    expect($test->course_id)->toBe($course->id)
        ->and($test->max_attempts)->toBe(2)
        ->and($test->lesson_id)->toBeNull();
});

test('a test may be scoped to a lesson within the same course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    $this->actingAs($instructor)
        ->post(route('tests.store', $course), [
            'title' => 'Lesson Quiz',
            'max_attempts' => 1,
            'lesson_id' => $lesson->id,
        ])
        ->assertRedirect();

    expect(Test::where('title', 'Lesson Quiz')->sole()->lesson_id)->toBe($lesson->id);
});

test('a lesson from another course cannot be attached', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $otherLesson = Lesson::factory()->create();

    $this->actingAs($instructor)
        ->post(route('tests.store', $course), [
            'title' => 'Bad Quiz',
            'max_attempts' => 1,
            'lesson_id' => $otherLesson->id,
        ])
        ->assertSessionHasErrors('lesson_id');
});

test('creating a test requires a title and attempts', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    $this->actingAs($instructor)
        ->post(route('tests.store', $course), ['title' => '', 'max_attempts' => ''])
        ->assertSessionHasErrors(['title', 'max_attempts']);
});

test('the owner can update a test', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create(['title' => 'Old']);

    $this->actingAs($instructor)
        ->put(route('tests.update', $test), [
            'title' => 'Renamed',
            'max_attempts' => 3,
        ])
        ->assertRedirect(route('tests.index', $course->slug));

    expect($test->fresh()->title)->toBe('Renamed')
        ->and($test->fresh()->max_attempts)->toBe(3);
});

test('the owner can soft-delete a test', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();

    $this->actingAs($instructor)->delete(route('tests.destroy', $test))->assertRedirect();

    expect($test->fresh()->trashed())->toBeTrue();
});

test('a non-owner cannot create a test', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)
        ->post(route('tests.store', $course), ['title' => 'X', 'max_attempts' => 1])
        ->assertForbidden();
});

test('a guest is redirected from test creation', function (): void {
    $course = Course::factory()->create();

    $this->post(route('tests.store', $course), ['title' => 'X', 'max_attempts' => 1])
        ->assertRedirect(route('login'));
});
