<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonCompletion>
 */
class LessonCompletionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'completed_at' => now(),
        ];
    }
}
