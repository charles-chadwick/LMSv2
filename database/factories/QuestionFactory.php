<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Test;
use Database\Seeders\RickAndMortyDialogue;
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
            'type' => QuestionType::MultipleChoice,
            'points' => fake()->numberBetween(1, 5),
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Replace the Faker prompt with a Rick and Morty dialogue line, phrased as a question.
     */
    public function readableContent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'prompt' => rtrim(RickAndMortyDialogue::next(), '.?!').'?',
        ]);
    }

    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::TrueFalse,
        ]);
    }

    public function shortAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::ShortAnswer,
        ]);
    }

    public function essay(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::Essay,
        ]);
    }
}
