<?php

namespace App\Enums;

enum NotificationType: string
{
    case NewQuestion = 'new_question';
    case NewReply = 'new_reply';
    case NewMessage = 'new_message';

    public function label(): string
    {
        return match ($this) {
            self::NewQuestion => 'Questions',
            self::NewReply => 'Replies',
            self::NewMessage => 'Messages',
        };
    }

    /**
     * Value/label option pairs for the notifications type filter.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
