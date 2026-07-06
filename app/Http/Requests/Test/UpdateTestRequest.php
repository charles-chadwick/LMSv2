<?php

namespace App\Http\Requests\Test;

use App\Models\Test;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lesson_id' => ['nullable', 'integer', Rule::in($this->courseLessonIds())],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:255'],
            'passing_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'available_from' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:available_from'],
        ];
    }

    /**
     * Lesson ids that belong to the test's course, so a test can only be
     * attached to a lesson within its own course.
     *
     * @return array<int, int>
     */
    protected function courseLessonIds(): array
    {
        /** @var Test $test */
        $test = $this->route('test');

        return $test->course->modules()
            ->with('lessons:id,module_id')
            ->get()
            ->flatMap(fn ($module) => $module->lessons->pluck('id'))
            ->all();
    }
}
