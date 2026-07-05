<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters users by role', function () {
    $student = User::factory()->student()->create();
    User::factory()->instructor()->create();

    expect(User::query()->withFilters(['role' => ['Student']])->pluck('id')->all())->toBe([$student->id]);
});

it('filters users by multiple roles', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    User::factory()->admin()->create();

    $ids = User::query()->withFilters(['role' => ['Student', 'Instructor']])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$student->id, $instructor->id])->sort()->values()->all());
});

it('filters users by derived status', function () {
    $active = User::factory()->create(['email_verified_at' => now()]);
    $invited = User::factory()->create(['email_verified_at' => null]);

    expect(User::query()->withFilters(['status' => ['Active']])->pluck('id')->all())->toBe([$active->id]);
    expect(User::query()->withFilters(['status' => ['Invited']])->pluck('id')->all())->toBe([$invited->id]);
    expect(User::query()->withFilters(['status' => ['Active', 'Invited']])->count())->toBe(2);
});

it('filters users by created_at range', function () {
    $inside = User::factory()->create(['created_at' => '2026-03-15 10:00:00']);
    User::factory()->create(['created_at' => '2026-01-01 10:00:00']);

    $ids = User::query()->withFilters(['created_at' => ['from' => '2026-03-01', 'to' => '2026-03-31']])->pluck('id')->all();

    expect($ids)->toBe([$inside->id]);
});

it('ignores unknown filter keys and empty values', function () {
    User::factory()->count(3)->create();

    expect(User::query()->withFilters(['bogus' => 'x', 'role' => [], 'status' => ''])->count())->toBe(3);
    expect(User::query()->withFilters(null)->count())->toBe(3);
});

it('combines filters with search', function () {
    $zoe = User::factory()->student()->create(['first_name' => 'Zoe', 'email' => 'zoe@example.com']);
    User::factory()->student()->create(['first_name' => 'Amy', 'email' => 'amy@example.com']);
    User::factory()->instructor()->create(['first_name' => 'Zane', 'email' => 'zane@example.com']);

    $ids = User::query()->withSearch('Z')->withFilters(['role' => ['Student']])->pluck('id')->all();

    expect($ids)->toBe([$zoe->id]);
});

it('does not let a filter group widen results when combined with another filter', function () {
    $activeStudent = User::factory()->student()->create(['email_verified_at' => now()]);
    $invitedStudent = User::factory()->student()->create(['email_verified_at' => null]);
    User::factory()->instructor()->create(['email_verified_at' => now()]);

    $count = User::query()->withFilters([
        'status' => ['Active', 'Invited'],
        'role' => ['Student'],
    ])->count();

    expect($count)->toBe(2)
        ->and(User::query()->withFilters([
            'status' => ['Active', 'Invited'],
            'role' => ['Student'],
        ])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$activeStudent->id, $invitedStudent->id])->sort()->values()->all());
});
