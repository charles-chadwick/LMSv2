<?php

use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('soft deletes a user and blocks their login', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)->delete(route('users.destroy', $student))
        ->assertRedirect(route('users.index'));

    expect(User::find($student->id))->toBeNull();
    expect(User::withTrashed()->find($student->id)->trashed())->toBeTrue();
});

it('blocks an admin from deleting their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

it('forbids an instructor from deleting another instructors student', function () {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($instructor)->delete(route('users.destroy', $student))->assertForbidden();
});

it('resends an invitation to a pending user', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->student()->unverified()->create();

    $this->actingAs($admin)->post(route('users.invite.resend', $pending))
        ->assertRedirect();

    Notification::assertSentTo($pending, UserInvitation::class);
});

it('does not resend an invitation to an already-active user', function () {
    $admin = User::factory()->admin()->create();
    $active = User::factory()->student()->create();

    $this->actingAs($admin)->post(route('users.invite.resend', $active))
        ->assertStatus(422);
});
