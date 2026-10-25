<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CatalogSeeder::class,
        ]);

        $department = Department::query()->where('name', 'Retail')->first();
        $warehouse = Warehouse::query()->where('code', 'STORE')->first();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@meridian.test'],
            [
                'name' => 'Meridian Admin',
                'password' => 'password',
                'department_id' => $department?->id,
                'warehouse_id' => $warehouse?->id,
                'is_active' => true,
            ],
        );

        $admin->syncRoles(['Admin']);
    }
}
