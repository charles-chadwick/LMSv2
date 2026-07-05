<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shows every user to an admin', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->instructor()->create();
    User::factory()->student()->create();

    $this->actingAs($admin)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->where('users.total', 3));
});

it('shows an instructor only the students they created', function () {
    $instructor = User::factory()->instructor()->create();
    $mine = User::factory()->student()->create(['created_by' => $instructor->id]);
    User::factory()->student()->create();

    $this->actingAs($instructor)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $mine->id));
});

it('forbids students from the user list', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('users.index'))->assertForbidden();
});
