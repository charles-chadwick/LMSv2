<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('renders the inbox and a conversation thread without JS errors', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    Message::factory()->for($conversation)->create(['sender_id' => $instructor->id, 'body' => 'Welcome to the course']);
    $this->actingAs($student);

    visit(route('conversations.index'))->assertNoJavaScriptErrors();

    visit(route('conversations.show', $conversation))
        ->assertSee('Welcome to the course')
        ->assertNoJavaScriptErrors();
});
