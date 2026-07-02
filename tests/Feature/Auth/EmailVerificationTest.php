<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('the verification prompt renders for unverified users', function (): void {
    $user = User::factory()->student()->unverified()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

test('verified users are redirected from the prompt to the dashboard', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertRedirect(route('dashboard'));
});

test('email can be verified with a valid signed link', function (): void {
    Event::fake();
    $user = User::factory()->student()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)->assertRedirect(route('dashboard').'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with an invalid hash', function (): void {
    $user = User::factory()->student()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('unverified users are redirected from the dashboard to the verification notice', function (): void {
    $user = User::factory()->student()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('verification.notice'));
});
