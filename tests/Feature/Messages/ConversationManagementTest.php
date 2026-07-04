<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('find-or-creates a single conversation per student-instructor pair', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($student)->post(route('conversations.store'), ['user_id' => $instructor->id])->assertRedirect();
    $this->actingAs($instructor)->post(route('conversations.store'), ['user_id' => $student->id])->assertRedirect();

    expect(Conversation::where('student_id', $student->id)->where('instructor_id', $instructor->id)->count())->toBe(1);
});

it('rejects same-role or self conversations', function () {
    $studentA = User::factory()->student()->create();
    $studentB = User::factory()->student()->create();

    $this->actingAs($studentA)->post(route('conversations.store'), ['user_id' => $studentB->id])->assertForbidden();
    $this->actingAs($studentA)->post(route('conversations.store'), ['user_id' => $studentA->id])->assertForbidden();
});

it('shows a conversation to a participant and marks their unread messages read', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    Message::factory()->for($conversation)->create(['sender_id' => $instructor->id, 'read_at' => null]);

    $this->actingAs($student)
        ->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page->component('Messages/Show')->where('conversation.id', $conversation->id));

    expect($conversation->messages()->whereNull('read_at')->count())->toBe(0);
});

it('forbids a non-participant from viewing a conversation', function () {
    $conversation = Conversation::factory()->create();
    $outsider = User::factory()->student()->create();

    $this->actingAs($outsider)->get(route('conversations.show', $conversation))->assertForbidden();
});

it('lists the user conversations ordered by last_message_at desc', function () {
    $student = User::factory()->student()->create();
    $older = Conversation::factory()->create(['student_id' => $student->id, 'last_message_at' => now()->subDay()]);
    $newer = Conversation::factory()->create(['student_id' => $student->id, 'last_message_at' => now()]);

    $this->actingAs($student)
        ->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Index')
            ->has('conversations', 2)
            ->where('conversations.0.id', $newer->id));
});
