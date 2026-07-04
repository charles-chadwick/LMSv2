<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('shares the unread messages count for the current user', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    Message::factory()->for($conversation)->count(2)->create(['sender_id' => $instructor->id, 'read_at' => null]);
    Message::factory()->for($conversation)->create(['sender_id' => $student->id, 'read_at' => null]); // own message: not unread for student

    $this->actingAs($student)
        ->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.unread_messages_count', 2));
});
