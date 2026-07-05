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
    $admin = User::factory()->admin()->create();
    $zoe = User::factory()->student()->create(['first_name' => 'Zoe']);
    User::factory()->student()->create(['first_name' => 'Amy']);
    User::factory()->instructor()->create(['first_name' => 'Zane']);

    $this->actingAs($admin)->get(route('users.index', ['search' => 'Z', 'filters' => ['role' => ['Student']]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $zoe->id));
});
