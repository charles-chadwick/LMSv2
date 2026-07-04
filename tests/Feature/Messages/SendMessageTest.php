<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets a participant send a message, broadcasts it, and notifies the recipient not the sender', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id, 'last_message_at' => null]);

    $this->actingAs($student)
        ->post(route('messages.store', $conversation), ['body' => 'Hi professor'])
        ->assertRedirect();

    expect($conversation->fresh()->last_message_at)->not->toBeNull()
        ->and($conversation->messages()->where('body', 'Hi professor')->exists())->toBeTrue();
    Event::assertDispatched(MessageSent::class);
    Notification::assertSentTo($instructor, NewMessage::class);
    Notification::assertNotSentTo($student, NewMessage::class);
});

it('forbids a non-participant from sending', function () {
    $conversation = Conversation::factory()->create();
    $outsider = User::factory()->student()->create();

    $this->actingAs($outsider)
        ->post(route('messages.store', $conversation), ['body' => 'nope'])
        ->assertForbidden();
});

it('rate-limits message sending to 30 per minute', function () {
    Event::fake([MessageSent::class]);
    Notification::fake();
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);

    foreach (range(1, 30) as $i) {
        $this->actingAs($student)->post(route('messages.store', $conversation), ['body' => "m$i"])->assertRedirect();
    }

    $this->actingAs($student)->post(route('messages.store', $conversation), ['body' => 'over'])->assertStatus(429);
});
