<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory(),
            'serial_number' => (string) Str::uuid(),
            'final_grade' => fake()->randomFloat(2, 60, 100),
            'issued_at' => now(),
        ];
    }
}
