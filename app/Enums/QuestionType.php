<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'Multiple Choice';
    case TrueFalse = 'True False';
    case ShortAnswer = 'Short Answer';
    case Essay = 'Essay';

    /**
     * Whether this question type can be auto-graded against a correct option.
     */
    public function isAutoGradable(): bool
    {
        return match ($this) {
            self::MultipleChoice, self::TrueFalse => true,
            self::ShortAnswer, self::Essay => false,
        };
    }

    /**
     * Fixed answer options for types whose choices are not author-defined
     * (e.g. True/False). Empty for free-form and non-gradable types.
     *
     * @return list<array{text: string, is_correct: bool}>
     */
    public function presetOptions(): array
    {
        return match ($this) {
            self::TrueFalse => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
            default => [],
        };
    }

    /**
     * Backing values of every auto-gradable type, for validation rules that
     * decide when answer options are required.
     *
     * @return list<string>
     */
    public static function autoGradableValues(): array
    {
        return array_values(array_map(
            fn (self $type): string => $type->value,
            array_filter(self::cases(), fn (self $type): bool => $type->isAutoGradable()),
        ));
    }

    /**
     * Selectable options for a question-type dropdown, carrying the metadata
     * the form needs to decide whether — and how — to collect answer options.
     *
     * @return list<array{value: string, label: string, gradable: bool, presetOptions: list<array{text: string, is_correct: bool}>}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->value,
            'gradable' => $type->isAutoGradable(),
            'presetOptions' => $type->presetOptions(),
        ], self::cases());
    }
}
