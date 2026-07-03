<?php

use App\Models\Course;
use App\Models\User;

it('matches courses on the title column', function () {
    $match = Course::factory()->create(['title' => 'Advanced Welding Techniques']);
    $other = Course::factory()->create(['title' => 'Intro to Pottery']);

    $ids = Course::query()->withSearch('welding')->pluck('id');

    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('matches courses on the slug column', function () {
    $match = Course::factory()->create(['slug' => 'unique-welding-slug-1']);
    $other = Course::factory()->create(['slug' => 'pottery-basics-2']);

    $ids = Course::query()->withSearch('unique-welding-slug')->pluck('id');

    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('matches courses by the instructor name relationship', function () {
    $instructor = User::factory()->instructor()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $match = Course::factory()->for($instructor, 'instructor')->create(['title' => 'Some Course']);
    $other = Course::factory()->create(['title' => 'Another Course']);

    $ids = Course::query()->withSearch('Lovelace')->pluck('id');

    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('returns all rows for an empty or whitespace term', function () {
    Course::factory()->count(3)->create();

    expect(Course::query()->withSearch('')->count())->toBe(3)
        ->and(Course::query()->withSearch('   ')->count())->toBe(3)
        ->and(Course::query()->withSearch(null)->count())->toBe(3);
});

it('ands the search with an existing constraint', function () {
    $mine = User::factory()->instructor()->create();
    $theirs = User::factory()->instructor()->create();
    $wanted = Course::factory()->for($mine, 'instructor')->create(['title' => 'Shared Keyword Course']);
    Course::factory()->for($theirs, 'instructor')->create(['title' => 'Shared Keyword Course Two']);

    $ids = Course::query()
        ->where('instructor_id', $mine->id)
        ->withSearch('Shared Keyword')
        ->pluck('id');

    expect($ids)->toContain($wanted->id)->toHaveCount(1);
});
