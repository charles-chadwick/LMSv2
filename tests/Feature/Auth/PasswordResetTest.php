<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('forgot password screen renders', function (): void {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

test('a reset link can be requested', function (): void {
    Notification::fake();
    $user = User::factory()->student()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('the reset password screen renders with a token', function (): void {
    Notification::fake();
    $user = User::factory()->student()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) {
        $this->get('/reset-password/'.$notification->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword'));

        return true;
    });
});

test('the password can be reset with a valid token', function (): void {
    Notification::fake();
    $user = User::factory()->student()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        return true;
    });
});
