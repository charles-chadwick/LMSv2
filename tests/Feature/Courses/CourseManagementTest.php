<?php

use App\Actions\ArchiveCourse;
use App\Actions\PublishCourse;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('publishing a draft course sets status and stamps published_at', function (): void {
    $course = Course::factory()->create([
        'status' => CourseStatus::Draft,
        'published_at' => null,
    ]);

    $result = PublishCourse::run($course);

    expect($result->status)->toBe(CourseStatus::Published)
        ->and($result->published_at)->not->toBeNull();
});

test('publishing does not overwrite an existing published_at', function (): void {
    $original = now()->subWeek();
    $course = Course::factory()->create([
        'status' => CourseStatus::Draft,
        'published_at' => $original,
    ]);

    PublishCourse::run($course);

    expect($course->fresh()->published_at->timestamp)->toBe($original->timestamp);
});

test('archiving a course sets status to archived', function (): void {
    $course = Course::factory()->published()->create();

    $result = ArchiveCourse::run($course);

    expect($result->status)->toBe(CourseStatus::Archived);
});

test('an instructor sees only their own courses on the index', function (): void {
    $instructor = User::factory()->instructor()->create();
    Course::factory()->for($instructor, 'instructor')->create(['title' => 'Mine']);
    Course::factory()->create(['title' => 'Someone elses']);

    $this->actingAs($instructor)
        ->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Courses/Index')
            ->has('courses', 1)
            ->where('courses.0.title', 'Mine')
        );
});

test('an admin sees every course on the index', function (): void {
    $admin = User::factory()->admin()->create();
    Course::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('courses', 3));
});

test('an instructor can store a new draft course', function (): void {
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)
        ->post(route('courses.store'), [
            'title' => 'Intro to Testing',
            'summary' => 'A short summary',
            'description' => 'A longer description',
            'level' => CourseLevel::Beginner->value,
        ])
        ->assertRedirect(route('courses.index'));

    $course = Course::where('title', 'Intro to Testing')->sole();
    expect($course->instructor_id)->toBe($instructor->id)
        ->and($course->status)->toBe(CourseStatus::Draft)
        ->and($course->slug)->toBe('intro-to-testing');
});

test('storing a course with a duplicate title gets a unique slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    Course::factory()->create(['title' => 'Duplicate', 'slug' => 'duplicate']);

    $this->actingAs($instructor)->post(route('courses.store'), [
        'title' => 'Duplicate',
        'level' => CourseLevel::Beginner->value,
    ])->assertRedirect(route('courses.index'));

    expect(Course::where('slug', 'duplicate-2')->exists())->toBeTrue();
});

test('storing a course requires a title and level', function (): void {
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)
        ->post(route('courses.store'), ['title' => '', 'level' => ''])
        ->assertSessionHasErrors(['title', 'level']);
});

test('an instructor can update their own course without changing the slug', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create([
        'title' => 'Original',
        'slug' => 'original',
    ]);

    $this->actingAs($instructor)
        ->put(route('courses.update', $course), [
            'title' => 'Renamed',
            'level' => CourseLevel::Advanced->value,
        ])
        ->assertRedirect(route('courses.index'));

    $course->refresh();
    expect($course->title)->toBe('Renamed')
        ->and($course->slug)->toBe('original');
});

test('an instructor can soft delete their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();

    $this->actingAs($instructor)
        ->delete(route('courses.destroy', $course))
        ->assertRedirect(route('courses.index'));

    expect($course->fresh()->trashed())->toBeTrue();
});

test('a student cannot access the course index', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('courses.index'))->assertForbidden();
});

test('a student cannot store a course', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->post(route('courses.store'), ['title' => 'X', 'level' => CourseLevel::Beginner->value])
        ->assertForbidden();
});

test('an instructor cannot update another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['title' => 'Theirs']);

    $this->actingAs($instructor)
        ->put(route('courses.update', $course), [
            'title' => 'Hacked',
            'level' => CourseLevel::Beginner->value,
        ])
        ->assertForbidden();
});

test('guests are redirected from the course index to login', function (): void {
    $this->get(route('courses.index'))->assertRedirect(route('login'));
});

test('an instructor can publish their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create([
        'status' => CourseStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($instructor)
        ->post(route('courses.publish', $course))
        ->assertRedirect();

    $course->refresh();
    expect($course->status)->toBe(CourseStatus::Published)
        ->and($course->published_at)->not->toBeNull();
});

test('an instructor can archive their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->published()->create();

    $this->actingAs($instructor)
        ->post(route('courses.archive', $course))
        ->assertRedirect();

    expect($course->fresh()->status)->toBe(CourseStatus::Archived);
});

test('an instructor cannot publish another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)
        ->post(route('courses.publish', $course))
        ->assertForbidden();
});

test('a student cannot publish a course', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->create();

    $this->actingAs($student)
        ->post(route('courses.publish', $course))
        ->assertForbidden();
});
