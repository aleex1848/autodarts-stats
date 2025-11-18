<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $rolePermissions = [
            RoleName::SuperAdmin->value => PermissionName::cases(),
            RoleName::Admin->value => PermissionName::cases(),
            RoleName::Spieler->value => [],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $role->syncPermissions(
                collect($permissions)->map(fn (PermissionName $permission) => $permission->value)->toArray()
            );
        }

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );

        $superAdmin->syncRoles(RoleName::SuperAdmin->value);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
            ],
        );

        $admin->syncRoles(RoleName::Admin->value);
    }
}
