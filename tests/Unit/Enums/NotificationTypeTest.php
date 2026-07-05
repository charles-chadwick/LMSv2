<?php

use App\Enums\NotificationType;

it('maps stored type strings to friendly select options', function () {
    expect(NotificationType::NewQuestion->value)->toBe('new_question')
        ->and(NotificationType::NewReply->value)->toBe('new_reply')
        ->and(NotificationType::NewMessage->value)->toBe('new_message');

    expect(NotificationType::options())->toBe([
        ['value' => 'new_question', 'label' => 'Questions'],
        ['value' => 'new_reply', 'label' => 'Replies'],
        ['value' => 'new_message', 'label' => 'Messages'],
    ]);
});
