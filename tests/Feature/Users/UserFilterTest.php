<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('filters the user list by role', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    User::factory()->instructor()->create();

    $this->actingAs($admin)->get(route('users.index', ['filters' => ['role' => ['Student']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $student->id));
});

it('filters the user list by derived status', function () {
    $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
    $invited = User::factory()->student()->create(['email_verified_at' => null]);
    User::factory()->student()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('users.index', ['filters' => ['status' => ['Invited']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $invited->id));
});

it('exposes filter options with role and status choices', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('filterOptions', 3)
            ->where('filterOptions.0.key', 'role')
            ->where('filterOptions.1.key', 'status')
            ->where('filterOptions.2.key', 'created_at'));
});

it('combines a role filter with search', function () {
    // Pin every searchable field (first_name, last_name, email) so the factory's
    // random last_name can't contain a "z" and accidentally match the 'Z' search.
    $admin = User::factory()->admin()->create(['first_name' => 'Ada', 'last_name' => 'Miller', 'email' => 'admin@example.com']);
    $zoe = User::factory()->student()->create(['first_name' => 'Zoe', 'last_name' => 'Miller', 'email' => 'zoe@example.com']);
    User::factory()->student()->create(['first_name' => 'Amy', 'last_name' => 'Miller', 'email' => 'amy@example.com']);
    User::factory()->instructor()->create(['first_name' => 'Zane', 'last_name' => 'Miller', 'email' => 'zane@example.com']);

    $this->actingAs($admin)->get(route('users.index', ['search' => 'Z', 'filters' => ['role' => ['Student']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $zoe->id));
});
