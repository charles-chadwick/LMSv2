<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the given role to the created user.
     */
    public function withRole(UserRole $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole(Role::findOrCreate($role->value, 'web'));
        });
    }

    /**
     * Create the user as an administrator.
     */
    public function admin(): static
    {
        return $this->withRole(UserRole::Admin);
    }

    /**
     * Create the user as an instructor.
     */
    public function instructor(): static
    {
        return $this->withRole(UserRole::Instructor);
    }

    /**
     * Create the user as a student.
     */
    public function student(): static
    {
        return $this->withRole(UserRole::Student);
    }
}
