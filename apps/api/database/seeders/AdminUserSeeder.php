<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Replace these credentials before seeding in production.
        $name = 'Admin User';
        $email = 'admin@example.com';
        $password = 'Password#123';

        if (! Role::query()->where('name', 'Admin')->where('guard_name', 'web')->exists()) {
            throw new RuntimeException(
                'Admin role is missing. Run RolePermissionSeeder before AdminUserSeeder.',
            );
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_active' => true,
            ],
        );

        $user->syncRoles(['Admin']);
    }
}
