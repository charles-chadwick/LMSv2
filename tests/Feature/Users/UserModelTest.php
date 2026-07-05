<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('soft deletes users and hides them from default queries', function () {
    $user = User::factory()->student()->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('records who created a user through the creator relationship', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create(['created_by' => $admin->id]);

    expect($student->creator->is($admin))->toBeTrue();
    expect($admin->createdUsers->pluck('id')->all())->toContain($student->id);
});

it('finds users by name and email through the search scope', function () {
    $match = User::factory()->create(['first_name' => 'Zoltan', 'email' => 'zoltan@example.com']);
    User::factory()->create(['first_name' => 'Someone', 'email' => 'someone@example.com']);

    $ids = User::withSearch('zoltan')->pluck('id')->all();

    expect($ids)->toContain($match->id)->toHaveCount(1);
});
