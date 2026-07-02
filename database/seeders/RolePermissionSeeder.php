<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * The permissions granted to each role, keyed by role name.
     *
     * @var array<string, list<string>>
     */
    protected array $rolePermissions = [
        'instructor' => [
            'create courses',
            'update courses',
            'delete courses',
            'publish courses',
            'manage course content',
            'enroll students',
            'manage assignments',
            'manage tests',
            'grade submissions',
            'view student progress',
            'participate in discussions',
            'moderate discussions',
        ],
        'student' => [
            'enroll in courses',
            'submit assignments',
            'submit tests',
            'participate in discussions',
            'view own progress',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect($this->rolePermissions)
            ->flatten()
            ->unique()
            ->values();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Admin implicitly receives every permission via Gate::before (see AppServiceProvider).
        Role::findOrCreate('admin', 'web');

        foreach ($this->rolePermissions as $role => $grantedPermissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($grantedPermissions);
        }
    }
}
