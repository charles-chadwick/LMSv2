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
}
