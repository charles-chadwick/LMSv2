<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Module;
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\RickAndMortyDialogue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'module_id' => Module::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'content' => fake()->paragraphs(4, true),
            'position' => fake()->numberBetween(0, 10),
            'duration_minutes' => fake()->numberBetween(5, 60),
        ];
    }

    /**
     * Replace Faker content with a real CS topic, matching slug and censored body.
     */
    public function readableContent(): static
    {
        return $this->state(function (array $attributes): array {
            $title = ComputerScienceTitles::nextLesson();

            return [
                'title' => $title,
                'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
                'content' => RickAndMortyDialogue::censoredBody(4, 8),
            ];
        });
    }
}
