<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\RickAndMortyDialogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Replace Faker content with a real CS topic and censored description.
     */
    public function readableContent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => ComputerScienceTitles::nextModule(),
            'description' => RickAndMortyDialogue::censoredBody(1, 2),
        ]);
    }
}
