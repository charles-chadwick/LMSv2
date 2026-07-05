<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an admin update a user name, email, and role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    $this->actingAs($admin)->put(route('users.management.update', $user), [
        'first_name' => 'Renamed',
        'last_name' => 'Person',
        'email' => 'renamed@example.com',
        'role' => UserRole::Instructor->value,
    ])->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->first_name)->toBe('Renamed');
    expect($user->email)->toBe('renamed@example.com');
    expect($user->hasRole(UserRole::Instructor->value))->toBeTrue();
});

it('lets an instructor edit their own student but not change the role', function () {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create(['created_by' => $instructor->id]);

    $this->actingAs($instructor)->put(route('users.management.update', $student), [
        'first_name' => 'Edited',
        'last_name' => 'Student',
        'email' => $student->email,
        'role' => UserRole::Instructor->value,
    ])->assertRedirect(route('users.index'));

    $student->refresh();
    expect($student->first_name)->toBe('Edited');
    expect($student->hasRole(UserRole::Student->value))->toBeTrue();
});

it('forbids an instructor from editing another instructors student', function () {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($instructor)->put(route('users.management.update', $student), [
        'first_name' => 'Nope',
        'last_name' => 'Student',
        'email' => $student->email,
    ])->assertForbidden();
});
