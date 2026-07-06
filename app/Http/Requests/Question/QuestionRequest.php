<?php

namespace App\Http\Requests\Question;

use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'options' => ['array', Rule::requiredIf(fn (): bool => in_array($this->input('type'), QuestionType::autoGradableValues(), true))],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.text' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['boolean'],
        ];
    }

    /**
     * Auto-gradable questions need at least one option flagged as correct so
     * ScoreTestAttempt has something to grade against.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = QuestionType::tryFrom((string) $this->input('type'));

                if ($type?->isAutoGradable() !== true) {
                    return;
                }

                $hasCorrect = collect($this->input('options', []))
                    ->contains(fn (array $option): bool => (bool) ($option['is_correct'] ?? false));

                if (! $hasCorrect) {
                    $validator->errors()->add('options', 'Mark at least one option as correct.');
                }
            },
        ];
    }
}
