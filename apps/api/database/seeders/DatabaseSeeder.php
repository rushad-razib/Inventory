<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@meridian.test'],
            [
                'name' => 'Meridian Admin',
                'password' => 'password',
            ],
        );

        $admin->syncRoles(['Admin']);
    }
}
