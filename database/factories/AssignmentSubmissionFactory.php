<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory()->student(),
            'content' => fake()->paragraphs(2, true),
            'status' => 'submitted',
            'attempt' => 1,
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
            'submitted_at' => now(),
            'graded_at' => null,
        ];
    }

    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'score' => fake()->randomFloat(2, 0, 100),
            'feedback' => fake()->sentence(),
            'graded_by' => User::factory()->instructor(),
            'graded_at' => now(),
        ]);
    }
}
