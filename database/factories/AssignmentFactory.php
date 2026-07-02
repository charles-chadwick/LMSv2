<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraphs(2, true),
            'points_possible' => fake()->randomElement([50, 100, 150, 200]),
            'due_at' => now()->addDays(fake()->numberBetween(3, 30)),
        ];
    }
}
