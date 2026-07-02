<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Administrators that must always exist, keyed by their Rick and Morty
     * character id so the matching avatar can be attached.
     *
     * @var array<string, int>
     */
    protected array $admins = [
        'Slow Rick' => 328,
        'Doofus Rick' => 103,
        'Reverse Giraffe' => 280,
        'President Curtis' => 347,
    ];

    /**
     * Seed users from the Rick and Morty character pool and attach avatars.
     *
     * Exposes the created instructors and students on the container so the
     * DatabaseSeeder can build courses around them.
     */
    public function run(): void
    {
        $admins = $this->createAdmins();
        $instructors = $this->createFromPool(UserRole::Instructor, 20);
        $students = $this->createFromPool(UserRole::Student, 250);

        $this->command?->info(sprintf(
            'Seeded %d admins, %d instructors, %d students.',
            $admins->count(),
            $instructors->count(),
            $students->count(),
        ));

        app()->instance('seed.instructors', $instructors);
        app()->instance('seed.students', $students);
    }

    /**
     * Create the always-present named administrators with fixed avatars.
     *
     * @return Collection<int, User>
     */
    protected function createAdmins(): Collection
    {
        return collect($this->admins)->map(function (int $characterId, string $name): User {
            RickAndMortyCharacters::markUsed($characterId);

            $user = User::factory()->admin()->create([
                'name' => $name,
                'email' => $this->emailFor($name, $characterId),
            ]);

            $user->attachAvatar(RickAndMortyCharacters::avatarPath($characterId));

            return $user;
        })->values();
    }

    /**
     * Create the given number of users for a role, drawing names and avatars
     * from the shuffled Rick and Morty character pool.
     *
     * @return Collection<int, User>
     */
    protected function createFromPool(UserRole $role, int $count): Collection
    {
        $users = collect();

        while ($users->count() < $count) {
            $character = RickAndMortyCharacters::next();
            $name = trim("{$character['first_name']} {$character['last_name']}");

            if ($name === '' || FilterData::hasBadWords($name)) {
                continue;
            }

            $user = User::factory()
                ->withRole($role)
                ->create([
                    'name' => $name,
                    'email' => $this->emailFor($name, $character['id']),
                ]);

            $user->attachAvatar(RickAndMortyCharacters::avatarPath($character['id']));

            $users->push($user);
        }

        return $users;
    }

    /**
     * Build a unique, deterministic email address for a character.
     */
    protected function emailFor(string $name, int $characterId): string
    {
        return Str::slug($name).'-'.$characterId.'@example.com';
    }
}
