<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters my courses by enrollment status', function () {
    $user = User::factory()->student()->create();
    $active = Enrollment::factory()->for($user, 'student')->create();
    Enrollment::factory()->completed()->for($user, 'student')->create();

    actingAs($user)->get(route('enrollments.index', ['filters' => ['status' => ['Active']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollments.total', 1)
            ->where('enrollments.data.0.id', $active->id));
});

it('exposes a status filter option on my courses', function () {
    $user = User::factory()->student()->create();

    actingAs($user)->get(route('enrollments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 1)
            ->where('filterOptions.0.key', 'status'));
});

it('filters the course roster by enrollment status', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $completed = Enrollment::factory()->completed()->for($course)->create();
    Enrollment::factory()->for($course)->create();

    actingAs($instructor)->get(route('courses.roster', ['course' => $course, 'filters' => ['status' => ['Completed']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.id', $completed->id));
});

it('exposes a status filter option on the roster', function () {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    actingAs($instructor)->get(route('courses.roster', $course))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 1)
            ->where('filterOptions.0.key', 'status'));
});
