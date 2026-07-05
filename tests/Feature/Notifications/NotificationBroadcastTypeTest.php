<?php

use App\Enums\NotificationType;
use App\Notifications\NewDiscussionQuestion;
use App\Notifications\NewDiscussionReply;
use App\Notifications\NewMessage;

it('exposes a stable broadcastType matching the stored type', function (string $class, NotificationType $type) {
    $reflection = new ReflectionClass($class);
    $instance = $reflection->newInstanceWithoutConstructor();

    expect($instance->broadcastType())->toBe($type->value);
})->with([
    [NewMessage::class, NotificationType::NewMessage],
    [NewDiscussionQuestion::class, NotificationType::NewQuestion],
    [NewDiscussionReply::class, NotificationType::NewReply],
]);
