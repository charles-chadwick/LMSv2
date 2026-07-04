<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows messaging between opposite roles but not same role or self', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $otherStudent = User::factory()->student()->create();

    $this->actingAs($student)->get(route('users.show', $instructor))
        ->assertInertia(fn (Assert $page) => $page->where('can_message', true));

    $this->actingAs($student)->get(route('users.show', $otherStudent))
        ->assertInertia(fn (Assert $page) => $page->where('can_message', false));

    $this->actingAs($student)->get(route('users.show', $student))
        ->assertInertia(fn (Assert $page) => $page->where('can_message', false));
});
