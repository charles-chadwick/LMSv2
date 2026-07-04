<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

it('resolves the other participant relative to a given user', function () {
    $student = User::factory()->create();
    $instructor = User::factory()->create();
    $conversation = Conversation::factory()->create(['student_id' => $student->id, 'instructor_id' => $instructor->id]);

    expect($conversation->otherParticipant($student)->id)->toBe($instructor->id)
        ->and($conversation->otherParticipant($instructor)->id)->toBe($student->id)
        ->and($conversation->hasParticipant($student))->toBeTrue()
        ->and($conversation->hasParticipant(User::factory()->create()))->toBeFalse();
});

it('exposes its latest message', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->for($conversation)->create(['body' => 'first']);
    $last = Message::factory()->for($conversation)->create(['body' => 'second']);

    expect($conversation->refresh()->latestMessage->id)->toBe($last->id);
});
