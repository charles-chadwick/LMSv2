<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Support\CauserResolver;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

use function fake;

class UserTableSeeder extends Seeder
{
    /**
     * The number of staff and student records seeded alongside the Super Admins.
     */
    private const int STAFF_AND_STUDENT_COUNT = 250;

    /**
     * The number of seeded staff that should be Instructors. The remainder are Students.
     */
    private const int INSTRUCTOR_COUNT = 25;

    /**
     * The three consistent Super Admins, keyed to their Rick and Morty character.
     *
     * @var array<int, array{id: int, first_name: string, last_name: string}>
     */
    private array $super_admins = [
        ['id' => 347, 'first_name' => 'President', 'last_name' => 'Curtis'],
        ['id' => 103, 'first_name' => 'Doofus', 'last_name' => 'Rick'],
        ['id' => 328, 'first_name' => 'Slow', 'last_name' => 'Rick'],
    ];

    /**
     * Seed the users table from the local Rick and Morty character data.
     *
     * Three consistent Super Admins are always created first, followed by a
     * deterministic set of Instructors and Students drawn from the remaining
     * characters. Every user is given their character's avatar from
     * `database/rickandmorty/avatars`, kept full sized with a thumbnail conversion.
     */
    public function run(): void
    {
        $super_admins = $this->seedSuperAdmins();

        $this->seedStaffAndStudents($super_admins->first());
    }

    /**
     * Create the three consistent Super Admins.
     *
     * @return Collection<int, User>
     */
    private function seedSuperAdmins(): Collection
    {
        echo "\nAdding Super Admins\n";

        return collect($this->super_admins)->map(function (array $character): User {
            $super_admin = User::factory()->create([
                'first_name' => $character['first_name'],
                'last_name' => $character['last_name'],
                'email' => $this->emailFor($character['first_name'], $character['last_name']),
                'created_at' => '2020-01-01 00:00:00',
                'updated_at' => '2020-01-01 00:00:00',
            ]);

            $super_admin->assignRole(UserRole::Admin->value);

            RickAndMortyCharacters::markUsed($character['id']);
            $this->attachAvatar($super_admin, $character['id']);

            echo '.';

            return $super_admin;
        });
    }

    /**
     * Create the Instructors and Students from the remaining characters.
     */
    private function seedStaffAndStudents(User $causer): void
    {
        app(CauserResolver::class)->setCauser($causer);

        echo "\nAdding Staff and Students\n";

        RickAndMortyCharacters::remaining()
            ->take(self::STAFF_AND_STUDENT_COUNT)
            ->each(function (array $character, int $index) use ($causer): void {
                $role = $index < self::INSTRUCTOR_COUNT ? UserRole::Instructor : UserRole::Student;
                $created_at = fake()->dateTimeBetween($causer->created_at, '-1 year');

                $user = User::factory()->create([
                    'first_name' => $character['first_name'],
                    'last_name' => $character['last_name'],
                    'email' => $this->emailFor($character['first_name'], $character['last_name'], $character['id']),
                    'created_at' => $created_at,
                    'updated_at' => $created_at,
                ]);

                $user->assignRole($role->value);

                RickAndMortyCharacters::markUsed($character['id']);
                $this->attachAvatar($user, $character['id']);

                echo '.';
            });

        echo "\n";
    }

    /**
     * Build a unique, lower-cased example email address for a character.
     */
    private function emailFor(string $first_name, string $last_name, ?int $character_id = null): string
    {
        $local_part = $character_id === null
            ? "{$first_name}.{$last_name}"
            : "{$first_name}.{$last_name}.{$character_id}";

        return str($local_part)
            ->lower()
            ->replaceMatches('/[^a-z0-9!#$%&\'*+\/=?^_`{|}~.-]/', '')
            ->replaceMatches('/\.+/', '.')
            ->trim('.')
            ->append('@example.com')
            ->value();
    }

    /**
     * Attach a character's local avatar to the user, kept full sized with a thumbnail.
     */
    private function attachAvatar(User $user, int $character_id): void
    {
        $avatar_path = RickAndMortyCharacters::avatarPath($character_id);

        if (! file_exists($avatar_path)) {
            return;
        }

        try {
            $user->addMedia($avatar_path)
                ->preservingOriginal()
                ->toMediaCollection('avatars');
        } catch (FileDoesNotExist|FileIsTooBig $exception) {
            echo $exception->getMessage().PHP_EOL;
        }
    }
}
