<?php

use App\Models\Concerns\Filters\ExactFilter;
use App\Models\Concerns\Filters\RelationFilter;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters an exact column by a scalar value', function () {
    $match = User::factory()->create(['email' => 'match@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $query = User::query();
    (new ExactFilter('email'))->apply($query, 'match@example.com');

    expect($query->pluck('id')->all())->toBe([$match->id]);
});

it('filters an exact column by an array of values with whereIn', function () {
    $a = User::factory()->create(['email' => 'a@example.com']);
    $b = User::factory()->create(['email' => 'b@example.com']);
    User::factory()->create(['email' => 'c@example.com']);

    $query = User::query();
    (new ExactFilter('email'))->apply($query, ['a@example.com', 'b@example.com']);

    expect($query->pluck('id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('filters by a single related value', function () {
    $student = User::factory()->student()->create();
    User::factory()->instructor()->create();

    $query = User::query();
    (new RelationFilter('roles', 'name'))->apply($query, 'Student');

    expect($query->pluck('id')->all())->toBe([$student->id]);
});

it('filters by multiple related values with whereIn', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    User::factory()->admin()->create();

    $query = User::query();
    (new RelationFilter('roles', 'name'))->apply($query, ['Student', 'Instructor']);

    expect($query->pluck('id')->sort()->values()->all())
        ->toBe(collect([$student->id, $instructor->id])->sort()->values()->all());
});
