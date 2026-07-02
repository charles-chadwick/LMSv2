<?php

namespace Database\Factories;

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
            'status' => 'in_progress',
            'score' => null,
            'graded_by' => null,
            'started_at' => now(),
            'submitted_at' => null,
            'graded_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'score' => fake()->randomFloat(2, 0, 100),
            'graded_by' => User::factory()->instructor(),
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);
    }
}
