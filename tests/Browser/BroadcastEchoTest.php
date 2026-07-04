<?php

use App\Models\User;

it('initializes window.Echo without breaking the page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit(route('dashboard'));

    $page->assertNoJavaScriptErrors();

    expect($page->script('typeof window.Echo'))->not->toBe('undefined');
});
