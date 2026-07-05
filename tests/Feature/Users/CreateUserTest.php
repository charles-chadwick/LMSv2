<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an admin invite an instructor', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'first_name' => 'Ivy',
        'last_name' => 'Instructor',
        'email' => 'ivy@example.com',
        'role' => UserRole::Instructor->value,
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'ivy@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Instructor->value))->toBeTrue();
    expect($user->created_by)->toBe($admin->id);
    Notification::assertSentTo($user, UserInvitation::class);
});

it('forces the role to student when an instructor creates a user', function () {
    Notification::fake();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)->post(route('users.store'), [
        'first_name' => 'Sam',
        'last_name' => 'Student',
        'email' => 'sam@example.com',
        'role' => UserRole::Instructor->value,
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'sam@example.com')->exists())->toBeFalse();
});

it('lets an instructor invite a student', function () {
    Notification::fake();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor)->post(route('users.store'), [
        'first_name' => 'Sam',
        'last_name' => 'Student',
        'email' => 'sam@example.com',
        'role' => UserRole::Student->value,
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'sam@example.com')->firstOrFail();
    expect($user->hasRole(UserRole::Student->value))->toBeTrue();
    expect($user->created_by)->toBe($instructor->id);
});

it('forbids a student from creating users', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->post(route('users.store'), [
        'first_name' => 'No',
        'last_name' => 'Way',
        'email' => 'no@example.com',
        'role' => UserRole::Student->value,
    ])->assertForbidden();
});
