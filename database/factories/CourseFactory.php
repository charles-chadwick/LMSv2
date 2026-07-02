<?php

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->catchPhrase();

        return [
            'instructor_id' => User::factory()->instructor(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'status' => CourseStatus::Draft,
            'level' => fake()->randomElement(CourseLevel::cases()),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Archived,
        ]);
    }
}
