<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'time_limit_minutes' => fake()->randomElement([null, 15, 30, 60]),
            'max_attempts' => fake()->numberBetween(1, 3),
            'passing_score' => fake()->randomElement([60, 70, 80]),
            'available_from' => now(),
            'due_at' => now()->addDays(fake()->numberBetween(7, 30)),
        ];
    }
}
