<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the permissions, the four roles and the first administrator.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Rbac::permissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (Rbac::matrix() as $roleName => $permissions) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->syncPermissions($permissions);
        }

        $this->createFirstAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createFirstAdmin(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD are not set in .env: first admin was not created.');

            return;
        }

        // An existing account keeps its password; only the role and status are ensured.
        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name'),
                'password' => Hash::make($password),
            ],
        );

        $admin->forceFill([
            'is_active' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        $admin->syncRoles(Rbac::ADMIN);
    }
}
