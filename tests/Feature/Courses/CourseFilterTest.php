<?php

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters the courses index by status', function () {
    $admin = User::factory()->admin()->create();
    $published = Course::factory()->published()->create();
    Course::factory()->create(['status' => CourseStatus::Draft]);

    actingAs($admin)->get(route('courses.index', ['filters' => ['status' => ['Published']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.id', $published->id));
});

it('filters the courses index by level', function () {
    $admin = User::factory()->admin()->create();
    $beginner = Course::factory()->create(['level' => CourseLevel::Beginner]);
    Course::factory()->create(['level' => CourseLevel::Advanced]);

    actingAs($admin)->get(route('courses.index', ['filters' => ['level' => ['Beginner']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.id', $beginner->id));
});

it('combines a status filter with search on the courses index', function () {
    $admin = User::factory()->admin()->create();
    $wanted = Course::factory()->published()->create(['title' => 'Welding Fundamentals']);
    Course::factory()->create(['status' => CourseStatus::Draft, 'title' => 'Welding Basics']);
    Course::factory()->published()->create(['title' => 'Ceramics 101']);

    actingAs($admin)->get(route('courses.index', ['search' => 'welding', 'filters' => ['status' => ['Published']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.id', $wanted->id));
});

it('exposes status and level filter options on the courses index', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get(route('courses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 2)
            ->where('filterOptions.0.key', 'status')
            ->where('filterOptions.1.key', 'level'));
});
