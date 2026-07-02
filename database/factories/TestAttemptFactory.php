<?php

namespace Database\Factories;

use App\Enums\TestAttemptStatus;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestAttempt>
 */
class TestAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_id' => Test::factory(),
            'user_id' => User::factory()->student(),
            'attempt_number' => 1,
            'status' => TestAttemptStatus::InProgress,
            'started_at' => now(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestAttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestAttemptStatus::Graded,
            'score' => fake()->randomFloat(2, 0, 100),
            'graded_by' => User::factory()->instructor(),
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);
    }
}
