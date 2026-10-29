<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait InteractsWithRbac
{
    protected function seedRbac(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
