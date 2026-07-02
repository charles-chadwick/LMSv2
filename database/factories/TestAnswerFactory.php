<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\TestAnswer;
use App\Models\TestAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestAnswer>
 */
class TestAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_attempt_id' => TestAttempt::factory(),
            'question_id' => Question::factory(),
            'question_option_id' => null,
            'answer_text' => fake()->sentence(),
            'points_awarded' => null,
            'is_correct' => null,
        ];
    }
}
