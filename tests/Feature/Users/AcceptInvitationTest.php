<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('accepts an invitation, sets the password, verifies email, and logs in', function () {
    $user = User::factory()->student()->unverified()->create();
    $token = Password::createToken($user);

    $response = $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Str0ng-pass!',
        'password_confirmation' => 'Str0ng-pass!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user->fresh());
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an invalid invitation token', function () {
    $user = User::factory()->student()->unverified()->create();

    $this->post(route('invitation.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'Str0ng-pass!',
        'password_confirmation' => 'Str0ng-pass!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
