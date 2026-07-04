<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('an instructor can view the roster of their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $enrollment = Enrollment::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Courses/Roster')
            ->has('students.data', 1)
            ->where('students.data.0.id', $enrollment->id)
            ->where('students.data.0.user.id', $enrollment->user_id)
            ->has('students.data.0.user.avatar_thumb')
            ->where('students.total', 1)
        );
});

test('the roster paginates enrolled students', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    Enrollment::factory()->count(25)->for($course)->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 20)
            ->where('students.total', 25)
            ->where('students.last_page', 2)
        );
});

test('an instructor cannot view the roster of another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->published()->create();

    $this->actingAs($instructor)
        ->get(route('courses.roster', $otherCourse))
        ->assertForbidden();
});

test('a student cannot view a course roster', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->get(route('courses.roster', $course))
        ->assertForbidden();
});

test('the roster no longer ships the full student list', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    $this->actingAs($instructor)
        ->get(route('courses.roster', $course))
        ->assertInertia(fn ($page) => $page->missing('enrollable_students'));
});

test('the student search returns matching enrollable students by name', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    $match = User::factory()->student()->create(['first_name' => 'Albert', 'last_name' => 'Einstein']);
    User::factory()->student()->create(['first_name' => 'Marie', 'last_name' => 'Curie']);

    $this->actingAs($instructor)
        ->getJson(route('courses.roster.search', ['course' => $course, 'q' => 'einstein']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $match->id)
        ->assertJsonPath('0.name', 'Albert Einstein');
});

test('the student search excludes students already active or completed in the course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    $enrolled = User::factory()->student()->create(['first_name' => 'Zed', 'last_name' => 'Enrolled']);
    Enrollment::factory()->for($enrolled, 'student')->for($course)->create([
        'status' => EnrollmentStatus::Active,
    ]);
    $available = User::factory()->student()->create(['first_name' => 'Zed', 'last_name' => 'Available']);

    $this->actingAs($instructor)
        ->getJson(route('courses.roster.search', ['course' => $course, 'q' => 'Zed']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $available->id);
});

test('the student search returns nothing for a blank query', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    User::factory()->student()->create();

    $this->actingAs($instructor)
        ->getJson(route('courses.roster.search', ['course' => $course, 'q' => '   ']))
        ->assertOk()
        ->assertJsonCount(0);
});

test('the student search caps the number of results', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    User::factory()->count(25)->student()->create(['first_name' => 'Searchable', 'last_name' => 'Person']);

    $this->actingAs($instructor)
        ->getJson(route('courses.roster.search', ['course' => $course, 'q' => 'Searchable']))
        ->assertOk()
        ->assertJsonCount(20);
});

test('an instructor cannot search students for another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $otherCourse = Course::factory()->published()->create();

    $this->actingAs($instructor)
        ->getJson(route('courses.roster.search', ['course' => $otherCourse, 'q' => 'a']))
        ->assertForbidden();
});

test('a student cannot search the enrollment pool', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->getJson(route('courses.roster.search', ['course' => $course, 'q' => 'a']))
        ->assertForbidden();
});

test('a guest cannot search the enrollment pool', function (): void {
    $course = Course::factory()->published()->create();

    $this->get(route('courses.roster.search', ['course' => $course, 'q' => 'a']))
        ->assertRedirect(route('login'));
});
