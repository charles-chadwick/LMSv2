<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\NewDiscussionQuestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function enrolledStudent(Course $course): User
{
    $student = User::factory()->student()->create();
    Enrollment::factory()->for($student, 'student')->for($course)->create();

    return $student;
}

it('lets an enrolled student create a course-level discussion and notifies the instructor', function () {
    Notification::fake();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $student = enrolledStudent($course);

    $this->actingAs($student)
        ->post(route('discussions.store', $course), ['title' => 'Why?', 'body' => 'Explain please'])
        ->assertRedirect();

    $discussion = Discussion::firstWhere('title', 'Why?');
    expect($discussion)->not->toBeNull()->and($discussion->lesson_id)->toBeNull();
    Notification::assertSentTo($instructor, NewDiscussionQuestion::class);
});

it('forbids a non-enrolled user from creating a discussion', function () {
    $course = Course::factory()->create();
    $outsider = User::factory()->student()->create();

    $this->actingAs($outsider)
        ->post(route('discussions.store', $course), ['title' => 'x', 'body' => 'y'])
        ->assertForbidden();
});

it('rejects a lesson_id that does not belong to the course', function () {
    $course = Course::factory()->create();
    $student = enrolledStudent($course);
    $foreignLesson = Lesson::factory()->create();

    $this->actingAs($student)
        ->post(route('discussions.store', $course), ['title' => 'x', 'body' => 'y', 'lesson_id' => $foreignLesson->id])
        ->assertSessionHasErrors('lesson_id');
});

it('lists course-level discussions pinned first and excludes lesson-level ones', function () {
    $course = Course::factory()->create();
    $student = enrolledStudent($course);
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();
    $plain = Discussion::factory()->for($course)->create(['title' => 'plain']);
    $pinned = Discussion::factory()->for($course)->pinned()->create(['title' => 'pinned']);
    Discussion::factory()->for($course)->create(['title' => 'lesson-q', 'lesson_id' => $lesson->id]);

    $this->actingAs($student)
        ->get(route('discussions.index', $course))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Discussions/Index')
            ->has('discussions.data', 2)
            ->where('discussions.data.0.title', 'pinned'));
});

it('shows a discussion thread', function () {
    $course = Course::factory()->create();
    $student = enrolledStudent($course);
    $discussion = Discussion::factory()->for($course)->create();

    $this->actingAs($student)
        ->get(route('discussions.show', $discussion))
        ->assertInertia(fn (Assert $page) => $page->component('Discussions/Show')
            ->where('discussion.id', $discussion->id));
});
