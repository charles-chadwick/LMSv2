<?php

namespace App\Http\Requests\Discussion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscussionRequest extends FormRequest
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
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'lesson_id' => [
                'nullable',
                Rule::exists('lessons', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'module_id',
                        $course->modules()->select('id'),
                    ),
                ),
            ],
        ];
    }
}
