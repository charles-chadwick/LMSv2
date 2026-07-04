<?php

use App\Events\BroadcastPing;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

it('broadcasts on the owner private user channel with a message payload', function () {
    $user = User::factory()->create();

    $event = new BroadcastPing($user, 'pong');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastOn()->name)->toBe('private-App.Models.User.'.$user->id)
        ->and($event->broadcastWith())->toBe(['message' => 'pong']);
});

it('authorizes the owner of a private user channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])
        ->assertOk();
});

it('denies a user access to another users private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$other->id,
        ])
        ->assertForbidden();
});
