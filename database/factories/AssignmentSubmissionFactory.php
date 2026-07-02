<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
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
            'status' => SubmissionStatus::Submitted,
            'attempt' => 1,
            'submitted_at' => now(),
        ];
    }

    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Graded,
            'score' => fake()->randomFloat(2, 0, 100),
            'feedback' => fake()->sentence(),
            'graded_by' => User::factory()->instructor(),
            'graded_at' => now(),
        ]);
    }
}
