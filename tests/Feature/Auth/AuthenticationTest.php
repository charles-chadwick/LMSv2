<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

test('authenticated user has roles shared to inertia', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.email', $user->email)
            ->where('auth.user.roles', ['Student'])
        );
});

test('user model requires email verification', function (): void {
    expect(new User)->toBeInstanceOf(MustVerifyEmail::class);
});

test('login screen renders for guests', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('users can authenticate with valid credentials', function (): void {
    $user = User::factory()->student()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with an invalid password', function (): void {
    $user = User::factory()->student()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login is rate limited after five failed attempts', function (): void {
    $user = User::factory()->student()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    expect(RateLimiter::tooManyAttempts(
        strtolower($user->email).'|127.0.0.1', 5
    ))->toBeTrue();
});

test('remember me sets the remember cookie', function (): void {
    $user = User::factory()->student()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ])->assertCookie(Auth::guard()->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

test('authenticated users can log out', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
