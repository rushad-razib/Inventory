<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.manage',
            'settings.manage',
            'catalog.manage',
            'suppliers.manage',
            'customers.manage',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'purchasing.create',
            'purchasing.approve',
            'purchasing.receive',
            'purchasing.settle',
            'supplier_returns.manage',
            'sales.create',
            'sales.return',
            'sales.refund.approve',
            'sales.void',
            'reports.view',
            'audit.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $bundles = [
            'Admin' => $permissions,
            'Manager' => [
                'purchasing.approve',
                'sales.refund.approve',
                'sales.void',
                'reports.view',
                'audit.view',
                'inventory.view',
            ],
            'Procurement' => [
                'suppliers.manage',
                'purchasing.create',
                'purchasing.receive',
                'purchasing.settle',
                'supplier_returns.manage',
                'inventory.view',
                'reports.view',
                'catalog.manage',
            ],
            'Warehouse' => [
                'purchasing.receive',
                'inventory.view',
                'inventory.adjust',
                'inventory.transfer',
                'supplier_returns.manage',
            ],
            'Cashier' => [
                'sales.create',
                'sales.return',
                'customers.manage',
                'inventory.view',
            ],
        ];

        foreach ($bundles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
