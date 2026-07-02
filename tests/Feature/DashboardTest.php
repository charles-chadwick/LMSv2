<?php

use App\Models\User;

test('guests are redirected to login from the dashboard', function (): void {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('authenticated users can view the dashboard', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});
