<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_id' => Test::factory(),
            'prompt' => fake()->sentence().'?',
            'type' => 'multiple_choice',
            'points' => fake()->numberBetween(1, 5),
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'true_false',
        ]);
    }

    public function shortAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'short_answer',
        ]);
    }

    public function essay(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'essay',
        ]);
    }
}
