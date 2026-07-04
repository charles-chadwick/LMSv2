<?php

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;

it('shapes the new-message notification for database + broadcast', function () {
    $message = Message::factory()->create(['body' => 'hello there friend']);
    $notification = new NewMessage($message);
    $data = $notification->toArray(new User);

    expect($notification->via(new User))->toBe(['database', 'broadcast'])
        ->and($data)->toHaveKeys(['conversation_id', 'sender_name', 'excerpt', 'type'])
        ->and($data['type'])->toBe('new_message')
        ->and($data['conversation_id'])->toBe($message->conversation_id);
});
