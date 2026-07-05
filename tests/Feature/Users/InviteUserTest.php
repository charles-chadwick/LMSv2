<?php

use App\Actions\InviteUser;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('provisions an unverified user, assigns the role, and sends an invitation', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $user = InviteUser::run([
        'first_name' => 'New',
        'last_name' => 'Teacher',
        'email' => 'new.teacher@example.com',
    ], UserRole::Instructor, $admin);

    expect($user->created_by)->toBe($admin->id);
    expect($user->email_verified_at)->toBeNull();
    expect($user->hasRole(UserRole::Instructor->value))->toBeTrue();

    Notification::assertSentTo($user, UserInvitation::class);
});
