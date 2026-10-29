<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevUsersSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    /**
     * One test user per non-admin role. Local environment only.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('DevUsersSeeder skipped: the environment is not local.');

            return;
        }

        $users = [
            ['Sales Manager Demo', 'manager@crm.test', Rbac::SALES_MANAGER],
            ['Sales Rep Demo', 'rep@crm.test', Rbac::SALES_REP],
            ['Viewer Demo', 'viewer@crm.test', Rbac::VIEWER],
        ];

        $rows = [];

        foreach ($users as [$name, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
            $user->syncRoles($role);

            $rows[] = [$role, $email, self::PASSWORD];
        }

        $this->command?->table(['Role', 'Email', 'Password'], $rows);
    }
}
