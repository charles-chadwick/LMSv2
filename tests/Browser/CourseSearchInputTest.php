<?php

use App\Models\Course;
use App\Models\User;

it('filters the visible course rows as the user types', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Course::factory()->create(['title' => 'Welding Fundamentals']);
    Course::factory()->create(['title' => 'Ceramics 101']);

    $page = visit(route('courses.index'));

    $page->assertSee('Welding Fundamentals')
        ->assertSee('Ceramics 101')
        ->fill('input[type=search]', 'welding')
        ->wait(0.6)
        ->assertSee('Welding Fundamentals')
        ->assertDontSee('Ceramics 101')
        ->assertNoJavaScriptErrors();
});
