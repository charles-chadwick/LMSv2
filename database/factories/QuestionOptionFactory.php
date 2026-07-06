<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Database\Seeders\RickAndMortyDialogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'text' => fake()->sentence(3),
            'is_correct' => false,
            'position' => fake()->numberBetween(0, 4),
        ];
    }

    /**
     * Replace the Faker option text with a Rick and Morty dialogue line.
     */
    public function readableContent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'text' => RickAndMortyDialogue::next(),
        ]);
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
