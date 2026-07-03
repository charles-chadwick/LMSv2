<?php

use App\Models\User;

test('guests are redirected from the home route to login', function (): void {
    $this->get('/')->assertRedirect(route('login'));
});

test('authenticated users are redirected from the home route to the dashboard', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});
