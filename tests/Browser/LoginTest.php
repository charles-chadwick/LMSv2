<?php

use App\Models\User;

test('the login page renders without javascript errors', function () {
    $page = visit('/login');

    $page->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

test('a user can log in without javascript errors', function () {
    $user = User::factory()->create();

    // Regression guard: route() is called inside the <script setup> submit
    // handler. If it is not exposed globally, submitting throws
    // "route is not defined" and the login never completes.
    $page = visit('/login');

    $page->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('button[type=submit]') // the <h1> also reads "Log in", so target the button explicitly
        ->waitForText('Welcome back') // wait out the async Inertia POST + redirect
        ->assertNoJavaScriptErrors()
        ->assertPathIs('/dashboard');
});
