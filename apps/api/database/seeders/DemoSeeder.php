<?php

namespace Database\Seeders;

use App\Actions\ReceiveGoods;
use App\Actions\SavePurchaseOrder;
use App\Actions\SubmitPurchaseOrder;
use App\Enums\LotOwnership;
use App\Enums\PointEntryType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerPointEntry;
use App\Models\Department;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PointLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    private const PENDING_NOTE = 'demo:pending-inbox';

    private const MUG_NOTE = 'demo:mug-opening';

    private const SHELF_NOTE = 'demo:hs-shelf';

    public function run(): void
    {
        $this->company();
        $departments = $this->departments();
        $warehouses = $this->warehouses();
        $suppliers = $this->suppliers();
        $categories = $this->categories();
        $products = $this->products($categories, $suppliers);
        $users = $this->users($departments, $warehouses);
        $this->customers($users['amina']);
        $this->openingStock($users['nadia'], $warehouses, $suppliers, $products);
    }

    private function company(): void
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
    }

    /**
     * @return array<string, Department>
     */
    private function departments(): array
    {
        $departments = [];

        foreach (['Procurement', 'Warehouse', 'Store', 'Finance'] as $name) {
            $departments[$name] = Department::query()->firstOrCreate(['name' => $name]);
        }

        return $departments;
    }

    /**
     * @return array<string, Warehouse>
     */
    private function warehouses(): array
    {
        return [
            'DC' => Warehouse::query()->firstOrCreate(
                ['code' => 'DC'],
                [
                    'name' => 'Distribution Center',
                    'address' => 'Savar',
                    'is_default' => true,
                    'is_active' => true,
                ],
            ),
            'HS' => Warehouse::query()->firstOrCreate(
                ['code' => 'HS'],
                [
                    'name' => 'High Street Store',
                    'address' => 'Dhaka',
                    'is_default' => false,
                    'is_active' => true,
                ],
            ),
        ];
    }

    /**
     * @return array<string, Supplier>
     */
    private function suppliers(): array
    {
        $rows = [
            'HART-MILL' => ['Hart Mill Goods', SupplyMode::Outright, 30, 7],
            'UNILEVER-BD' => ['Unilever Bangladesh', SupplyMode::Consignment, 0, 3],
            'DELTA-HOME' => ['Delta Home Wholesale', SupplyMode::Outright, 15, 5],
        ];
        $suppliers = [];

        foreach ($rows as $code => [$name, $mode, $terms, $lead]) {
            $suppliers[$code] = Supplier::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'contact' => $name,
                    'email' => strtolower($code).'@hartandfield.test',
                    'phone' => '+8801711000099',
                    'address' => 'Dhaka',
                    'supply_mode' => $mode,
                    'payment_terms_days' => $terms,
                    'lead_time_days' => $lead,
                    'status' => SupplierStatus::Active,
                    'notes' => null,
                ],
            );
        }

        return $suppliers;
    }

    /**
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $home = Category::query()->firstOrCreate(['name' => 'Home'], ['parent_id' => null]);
        $categories = ['Home' => $home];

        foreach (['Kitchen', 'Bath', 'Cleaning', 'Pantry'] as $name) {
            $categories[$name] = Category::query()->firstOrCreate(
                ['name' => $name],
                ['parent_id' => $home->id],
            );
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Supplier>  $suppliers
     * @return array<string, Product>
     */
    private function products(array $categories, array $suppliers): array
    {
        $products = [];

        foreach ($this->catalog() as $row) {
            $product = Product::query()->firstOrCreate(
                ['sku' => $row['sku']],
                [
                    'barcode' => $row['barcode'],
                    'name' => $row['name'],
                    'description' => null,
                    'category_id' => $categories[$row['category']]->id,
                    'unit_of_measure' => 'each',
                    'sell_price' => $row['sell_price'],
                    'reorder_point' => $row['reorder_point'],
                    'reorder_quantity' => $row['reorder_quantity'],
                    'preferred_supplier_id' => $suppliers[$row['supplier']]->id,
                    'track_stock' => true,
                    'is_active' => true,
                ],
            );

            SupplierProduct::query()->firstOrCreate(
                [
                    'supplier_id' => $suppliers[$row['supplier']]->id,
                    'product_id' => $product->id,
                ],
                [
                    'supplier_sku' => $row['sku'],
                    'agreed_unit_cost' => $row['cost'],
                ],
            );

            $products[$row['sku']] = $product;
        }

        return $products;
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, Warehouse>  $warehouses
     * @return array<string, User>
     */
    private function users(array $departments, array $warehouses): array
    {
        $rows = [
            'amina' => ['Amina Rahman', 'amina@hartandfield.test', 'Admin', null, null],
            'yusuf' => ['Yusuf Karim', 'yusuf@hartandfield.test', 'Manager', 'Finance', null],
            'nadia' => ['Nadia Chowdhury', 'nadia@hartandfield.test', 'Procurement', 'Procurement', null],
            'karim' => ['Karim Hossain', 'karim@hartandfield.test', 'Warehouse', 'Warehouse', 'DC'],
            'lina' => ['Lina Ahmed', 'lina@hartandfield.test', 'Cashier', 'Store', 'HS'],
        ];
        $users = [];

        foreach ($rows as $key => [$name, $email, $role, $department, $warehouse]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'department_id' => $department === null ? null : $departments[$department]->id,
                    'warehouse_id' => $warehouse === null ? null : $warehouses[$warehouse]->id,
                    'is_active' => true,
                ],
            );
            $user->syncRoles([$role]);
            $users[$key] = $user;
        }

        return $users;
    }

    private function customers(User $actor): void
    {
        $rahim = Customer::query()->firstOrCreate(
            ['mobile' => '+8801711000001'],
            ['name' => 'Rahim Uddin', 'points_balance' => 0],
        );

        if ($rahim->points_balance === 0 && ! CustomerPointEntry::query()->where('customer_id', $rahim->id)->exists()) {
            DB::transaction(function () use ($rahim, $actor): void {
                app(PointLedger::class)->post($rahim, PointEntryType::Earn, 30, $rahim, $actor);
            });
        }

        Customer::query()->firstOrCreate(
            ['mobile' => '+8801711000002'],
            ['name' => null, 'points_balance' => 0],
        );
    }

    /**
     * @param  array<string, Warehouse>  $warehouses
     * @param  array<string, Supplier>  $suppliers
     * @param  array<string, Product>  $products
     */
    private function openingStock(User $nadia, array $warehouses, array $suppliers, array $products): void
    {
        $this->receiveConsignment(
            $nadia,
            $suppliers['UNILEVER-BD'],
            $warehouses['HS'],
            $products['SOAP-001'],
            6,
            'demo-soap-hs',
        );
        $this->receiveConsignment(
            $nadia,
            $suppliers['UNILEVER-BD'],
            $warehouses['DC'],
            $products['SOAP-001'],
            6,
            'demo-soap-dc',
        );

        $this->receiveOutright(
            $nadia,
            $suppliers['HART-MILL'],
            $warehouses['HS'],
            [['sku' => 'MUG-001', 'quantity' => 4]],
            $products,
            self::MUG_NOTE,
            'demo-mug-hs',
        );
        $this->receiveOutright(
            $nadia,
            $suppliers['HART-MILL'],
            $warehouses['DC'],
            [['sku' => 'MUG-001', 'quantity' => 4]],
            $products,
            'demo:mug-dc',
            'demo-mug-dc',
        );

        $this->receiveOutright(
            $nadia,
            $suppliers['HART-MILL'],
            $warehouses['HS'],
            [
                ['sku' => 'BOWL-001', 'quantity' => 5],
                ['sku' => 'PLATE-001', 'quantity' => 5],
                ['sku' => 'TUMBLER-001', 'quantity' => 5],
                ['sku' => 'TRAY-001', 'quantity' => 5],
                ['sku' => 'LINEN-001', 'quantity' => 5],
            ],
            $products,
            self::SHELF_NOTE,
            'demo-shelf-hs',
        );

        if (! PurchaseOrder::query()->where('notes', self::PENDING_NOTE)->exists()) {
            $order = app(SavePurchaseOrder::class)->create($nadia, [
                'supplier_id' => $suppliers['HART-MILL']->id,
                'destination_warehouse_id' => $warehouses['DC']->id,
                'notes' => self::PENDING_NOTE,
                'lines' => [
                    ['product_id' => $products['KETTLE-001']->id, 'quantity' => 5],
                    ['product_id' => $products['TOASTER-001']->id, 'quantity' => 5],
                    ['product_id' => $products['JUG-001']->id, 'quantity' => 8],
                    ['product_id' => $products['BOARD-001']->id, 'quantity' => 8],
                ],
            ]);
            app(SubmitPurchaseOrder::class)($nadia, $order, null);
        }
    }

    private function receiveConsignment(User $actor, Supplier $supplier, Warehouse $warehouse, Product $product, int $quantity, string $key): void
    {
        $exists = StockLot::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('ownership', LotOwnership::Consignment)
            ->exists();

        if ($exists) {
            return;
        }

        app(ReceiveGoods::class)($actor, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ],
        ], $key, hash('sha256', $key), null);
    }

    /**
     * @param  list<array{sku: string, quantity: int}>  $lines
     * @param  array<string, Product>  $products
     */
    private function receiveOutright(
        User $actor,
        Supplier $supplier,
        Warehouse $warehouse,
        array $lines,
        array $products,
        string $note,
        string $key,
    ): void {
        $order = PurchaseOrder::query()->where('notes', $note)->first();

        if ($order === null) {
            $order = app(SavePurchaseOrder::class)->create($actor, [
                'supplier_id' => $supplier->id,
                'destination_warehouse_id' => $warehouse->id,
                'notes' => $note,
                'lines' => array_map(
                    fn (array $line): array => [
                        'product_id' => $products[$line['sku']]->id,
                        'quantity' => $line['quantity'],
                    ],
                    $lines,
                ),
            ]);
            $order = app(SubmitPurchaseOrder::class)($actor, $order, null);
        }

        if ($order->status !== PurchaseOrderStatus::Approved) {
            return;
        }

        $order->load('lines');
        $remaining = $order->lines
            ->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'quantity' => $line->quantity_ordered - $line->quantity_received,
            ])
            ->filter(fn (array $line): bool => $line['quantity'] > 0)
            ->values()
            ->all();

        if ($remaining === []) {
            return;
        }

        app(ReceiveGoods::class)($actor, [
            'purchase_order_id' => $order->id,
            'lines' => $remaining,
        ], $key, hash('sha256', $key), null);
    }

    /**
     * @return list<array{sku: string, barcode: string, name: string, category: string, sell_price: int, reorder_point: int, reorder_quantity: int, supplier: string, cost: int}>
     */
    private function catalog(): array
    {
        $rows = [
            ['SOAP-001', '8941000000017', 'Castile Soap', 'Cleaning', 100, 2, 12, 'UNILEVER-BD', 60],
            ['MUG-001', '8941000000024', 'Stoneware Mug', 'Kitchen', 150, 2, 12, 'HART-MILL', 70],
            ['BOWL-001', '8941000000101', 'Serving Bowl', 'Kitchen', 400, 4, 20, 'HART-MILL', 250],
            ['PLATE-001', '8941000000102', 'Dinner Plate', 'Kitchen', 300, 4, 20, 'HART-MILL', 180],
            ['TUMBLER-001', '8941000000103', 'Glass Tumbler', 'Kitchen', 250, 4, 20, 'HART-MILL', 150],
            ['TRAY-001', '8941000000104', 'Oak Tray', 'Kitchen', 800, 4, 10, 'HART-MILL', 500],
            ['LINEN-001', '8941000000105', 'Table Linen', 'Kitchen', 600, 4, 10, 'HART-MILL', 340],
            ['KETTLE-001', '8941000000201', 'Stovetop Kettle', 'Kitchen', 2200, 0, 0, 'HART-MILL', 1400],
            ['TOASTER-001', '8941000000202', 'Toaster', 'Kitchen', 1800, 0, 0, 'HART-MILL', 1100],
            ['JUG-001', '8941000000203', 'Water Jug', 'Kitchen', 900, 0, 0, 'HART-MILL', 550],
            ['BOARD-001', '8941000000204', 'Chopping Board', 'Kitchen', 700, 0, 0, 'HART-MILL', 400],
        ];

        $fillers = [
            ['CUP-001', 'Tea Cup', 'Kitchen', 120, 'HART-MILL', 70],
            ['SAUCER-001', 'Saucer', 'Kitchen', 80, 'HART-MILL', 40],
            ['SPOON-001', 'Spoon Set', 'Kitchen', 220, 'HART-MILL', 110],
            ['FORK-001', 'Fork Set', 'Kitchen', 220, 'HART-MILL', 110],
            ['NAPKIN-001', 'Napkin Ring', 'Kitchen', 90, 'HART-MILL', 40],
            ['COASTER-001', 'Coaster Set', 'Kitchen', 160, 'HART-MILL', 80],
            ['SALT-001', 'Salt Mill', 'Pantry', 450, 'HART-MILL', 220],
            ['PEPPER-001', 'Pepper Mill', 'Pantry', 450, 'HART-MILL', 220],
            ['GRAVY-001', 'Gravy Boat', 'Kitchen', 380, 'HART-MILL', 190],
            ['DISH-001', 'Dish Soap', 'Cleaning', 90, 'UNILEVER-BD', 40],
            ['LAUNDRY-001', 'Laundry Powder', 'Cleaning', 180, 'UNILEVER-BD', 80],
            ['FLOOR-001', 'Floor Cleaner', 'Cleaning', 160, 'UNILEVER-BD', 70],
            ['GLASS-001', 'Glass Spray', 'Cleaning', 140, 'UNILEVER-BD', 60],
            ['HAND-001', 'Hand Wash', 'Bath', 110, 'UNILEVER-BD', 50],
            ['SHAMPOO-001', 'Shampoo', 'Bath', 200, 'UNILEVER-BD', 90],
            ['COND-001', 'Conditioner', 'Bath', 200, 'UNILEVER-BD', 90],
            ['LOTION-001', 'Body Lotion', 'Bath', 240, 'UNILEVER-BD', 100],
            ['PASTE-001', 'Toothpaste', 'Bath', 80, 'UNILEVER-BD', 30],
            ['SOFT-001', 'Fabric Softener', 'Cleaning', 170, 'UNILEVER-BD', 70],
            ['TOWEL-001', 'Cotton Towel', 'Bath', 350, 'DELTA-HOME', 160],
            ['MAT-001', 'Bath Mat', 'Bath', 420, 'DELTA-HOME', 200],
            ['CURTAIN-001', 'Shower Curtain', 'Bath', 680, 'DELTA-HOME', 320],
            ['DISHBOX-001', 'Soap Dish', 'Bath', 90, 'DELTA-HOME', 40],
            ['BOX-001', 'Storage Box', 'Home', 540, 'DELTA-HOME', 260],
            ['HANGER-001', 'Clothes Hanger', 'Home', 60, 'DELTA-HOME', 20],
            ['IRON-001', 'Ironing Board', 'Home', 1600, 'DELTA-HOME', 800],
            ['BIN-001', 'Dustbin', 'Home', 480, 'DELTA-HOME', 220],
            ['BASKET-001', 'Laundry Basket', 'Home', 720, 'DELTA-HOME', 340],
            ['DOORMAT-001', 'Door Mat', 'Home', 390, 'DELTA-HOME', 180],
        ];

        foreach ($fillers as $index => $filler) {
            $rows[] = [
                $filler[0],
                sprintf('8941000001%03d', $index + 1),
                $filler[1],
                $filler[2],
                $filler[3],
                0,
                0,
                $filler[4],
                $filler[5],
            ];
        }

        return array_map(fn (array $row): array => [
            'sku' => $row[0],
            'barcode' => $row[1],
            'name' => $row[2],
            'category' => $row[3],
            'sell_price' => $row[4],
            'reorder_point' => $row[5],
            'reorder_quantity' => $row[6],
            'supplier' => $row[7],
            'cost' => $row[8],
        ], $rows);
    }
}
