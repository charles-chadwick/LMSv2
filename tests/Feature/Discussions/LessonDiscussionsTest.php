<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('passes only this lesson\'s discussions to the lesson page', function () {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $otherLesson = Lesson::factory()->for($module)->create();
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();

    $mine = Discussion::factory()->for($course)->create(['lesson_id' => $lesson->id, 'title' => 'about this lesson']);
    Discussion::factory()->for($course)->create(['lesson_id' => $otherLesson->id, 'title' => 'other lesson']);
    Discussion::factory()->for($course)->create(['lesson_id' => null, 'title' => 'course level']);

    $this->actingAs($student)
        ->get(route('lessons.show', ['course' => $course, 'lesson' => $lesson]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('lessonDiscussions', 1)
            ->where('lessonDiscussions.0.title', 'about this lesson'));
});
