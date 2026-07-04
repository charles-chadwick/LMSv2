<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('broadcasts a sent message on its conversation channel with a shaped payload', function () {
    $message = Message::factory()->create();

    $event = new MessageSent($message);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe('private-conversations.'.$message->conversation_id)
        ->and($event->broadcastWith())->toMatchArray([
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
        ]);
    expect($event->broadcastWith()['sender'])->toHaveKey('id');
});

it('authorizes the conversation channel for a participant and denies an outsider', function () {
    $student = User::factory()->student()->create();
    $instructor = User::factory()->instructor()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);
    $outsider = User::factory()->student()->create();

    $this->actingAs($student)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-conversations.'.$conversation->id,
    ])->assertOk();

    $this->actingAs($outsider)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-conversations.'.$conversation->id,
    ])->assertForbidden();
});
