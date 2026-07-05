<?php

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters the catalog by level', function () {
    $user = User::factory()->student()->create();
    $beginner = Course::factory()->published()->create(['level' => CourseLevel::Beginner]);
    Course::factory()->published()->create(['level' => CourseLevel::Advanced]);

    actingAs($user)->get(route('catalog.index', ['filters' => ['level' => ['Beginner']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.id', $beginner->id));
});

it('searches the catalog by title', function () {
    $user = User::factory()->student()->create();
    $wanted = Course::factory()->published()->create(['title' => 'Welding Fundamentals']);
    Course::factory()->published()->create(['title' => 'Ceramics 101']);

    actingAs($user)->get(route('catalog.index', ['search' => 'welding']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.total', 1)
            ->where('courses.data.0.id', $wanted->id));
});

it('exposes only a level filter option on the catalog', function () {
    $user = User::factory()->student()->create();

    actingAs($user)->get(route('catalog.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 1)
            ->where('filterOptions.0.key', 'level'));
});
