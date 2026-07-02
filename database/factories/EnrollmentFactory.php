<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory(),
            'status' => EnrollmentStatus::Active,
            'progress_percentage' => 0,
            'enrolled_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Completed,
            'progress_percentage' => 100,
            'final_grade' => fake()->randomFloat(2, 60, 100),
            'completed_at' => now(),
        ]);
    }

    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Dropped,
        ]);
    }
}
