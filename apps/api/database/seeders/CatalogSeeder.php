<?php

namespace Database\Seeders;

use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::query()->firstOrCreate(
            ['currency' => 'BDT'],
            [
                'name' => 'Hart & Field',
                'po_approval_threshold' => 20000,
                'adjustment_approval_threshold' => 5000,
                'points_earn_per' => 100,
                'point_redeem_value' => 1,
            ],
        );

        $retail = Department::query()->firstOrCreate(['name' => 'Retail']);
        Department::query()->firstOrCreate(['name' => 'Warehouse']);

        $store = Warehouse::query()->firstOrCreate(
            ['code' => 'STORE'],
            [
                'name' => 'High Street Store',
                'address' => 'Dhaka',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        Warehouse::query()->firstOrCreate(
            ['code' => 'DC'],
            [
                'name' => 'Distribution Center',
                'address' => 'Savar',
                'is_default' => false,
                'is_active' => true,
            ],
        );

        $home = Category::query()->firstOrCreate(
            ['name' => 'Home'],
            ['parent_id' => null],
        );

        $kitchen = Category::query()->firstOrCreate(
            ['name' => 'Kitchen'],
            ['parent_id' => $home->id],
        );

        $supplier = Supplier::query()->firstOrCreate(
            ['code' => 'ACME'],
            [
                'name' => 'Acme Supplies',
                'contact' => 'Acme Desk',
                'email' => 'desk@acme.test',
                'phone' => '+8801711000000',
                'address' => 'Dhaka',
                'supply_mode' => SupplyMode::Outright,
                'payment_terms_days' => 30,
                'lead_time_days' => 7,
                'status' => SupplierStatus::Active,
                'notes' => null,
            ],
        );

        Product::query()->firstOrCreate(
            ['sku' => 'MUG-001'],
            [
                'barcode' => '8901001001001',
                'name' => 'Stoneware Mug',
                'description' => 'Phase 2 sample product',
                'category_id' => $kitchen->id,
                'unit_of_measure' => 'each',
                'sell_price' => 150,
                'reorder_point' => 10,
                'reorder_quantity' => 24,
                'preferred_supplier_id' => $supplier->id,
                'track_stock' => true,
                'is_active' => true,
            ],
        );

        // Expose for DatabaseSeeder to pin the admin.
        $this->command?->getOutput()?->writeln("Seeded department {$retail->id} and warehouse {$store->id}");
    }
}
