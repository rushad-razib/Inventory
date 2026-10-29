<?php

use App\Enums\LotOwnership;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    CompanySetting::query()->create([
        'name' => 'Hart & Field',
        'currency' => 'BDT',
        'po_approval_threshold' => 20000,
        'adjustment_approval_threshold' => 5000,
        'points_earn_per' => 100,
        'point_redeem_value' => 1,
    ]);
});

it('rejects procurement routes without authentication or permission', function () {
    $this->getJson('/api/v1/purchase-orders')->assertUnauthorized();

    $cashier = User::factory()->create();
    $cashier->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($cashier)->getJson('/api/v1/purchase-orders')->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/reorder-alerts')->assertForbidden();
    $this->actingAs($cashier)->postJson('/api/v1/goods-receipts', [])->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/approvals')->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/supplier-bills')->assertForbidden();
});

it('receives part of an order onto a new lot and the rest later', function () {
    [$warehouse, $product, $supplier] = procurementFixture();
    $opening = StockLot::query()->create([
        'supplier_id' => null,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'unit_cost' => 0,
        'ownership' => LotOwnership::Owned,
        'qty_received' => 3,
        'qty_on_hand' => 3,
        'qty_damaged' => 0,
        'received_at' => now()->subDay(),
    ]);
    StockBalance::query()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'on_hand' => 3,
        'damaged' => 0,
        'in_transit' => 0,
    ]);

    $buyer = procurementUser('Procurement');
    $order = draftOrder($buyer, $supplier, $warehouse, $product, 10);

    $this->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$order['id'].'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $first = $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $order['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 4],
        ],
    ], ['Idempotency-Key' => 'recv-4']);

    $first->assertCreated()
        ->assertJsonPath('data.bill.amount', 400)
        ->assertJsonPath('data.bill.status', 'open')
        ->assertJsonPath('data.bill.due_on', now()->addDays(30)->toDateString());

    $orderLine = PurchaseOrder::query()->find($order['id'])->lines()->first();

    expect($orderLine->quantity_received)->toBe(4)
        ->and(PurchaseOrder::query()->find($order['id'])->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and(StockBalance::query()->first()->on_hand)->toBe(7)
        ->and($opening->refresh()->qty_on_hand)->toBe(3)
        ->and(StockLot::query()->count())->toBe(2)
        ->and(SupplierBill::query()->count())->toBe(1);

    $receivedLot = StockLot::query()->where('goods_receipt_id', $first->json('data.id'))->first();

    expect($receivedLot->qty_on_hand)->toBe(4)
        ->and($receivedLot->unit_cost)->toBe(100)
        ->and($receivedLot->ownership)->toBe(LotOwnership::Owned)
        ->and($receivedLot->supplier_id)->toBe($supplier->id);

    $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $order['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 6],
        ],
    ], ['Idempotency-Key' => 'recv-6'])->assertCreated();

    expect(PurchaseOrder::query()->find($order['id'])->status)->toBe(PurchaseOrderStatus::Received)
        ->and(StockBalance::query()->first()->on_hand)->toBe(13)
        ->and(StockLot::query()->count())->toBe(3)
        ->and($opening->refresh()->qty_on_hand)->toBe(3);
});

it('posts a consignment receipt without a supplier bill', function () {
    [$warehouse, $product] = procurementFixture();
    $supplier = Supplier::query()->create([
        'code' => 'UNI',
        'name' => 'Unilever',
        'supply_mode' => SupplyMode::Consignment,
        'payment_terms_days' => 0,
        'status' => SupplierStatus::Active,
    ]);
    SupplierProduct::query()->create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'agreed_unit_cost' => 40,
    ]);

    $buyer = procurementUser('Procurement');

    $receipt = $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 5],
        ],
    ], ['Idempotency-Key' => 'consign-1']);

    $receipt->assertCreated()->assertJsonPath('data.bill', null);

    $lot = StockLot::query()->first();

    expect($lot->ownership)->toBe(LotOwnership::Consignment)
        ->and($lot->qty_on_hand)->toBe(5)
        ->and($lot->unit_cost)->toBe(40)
        ->and(SupplierBill::query()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(0);
});

it('does not double a lot or a bill when a receipt is replayed', function () {
    [$warehouse, $product, $supplier] = procurementFixture();
    $buyer = procurementUser('Procurement');
    $order = submittedOrder($buyer, $supplier, $warehouse, $product, 4);
    $body = [
        'purchase_order_id' => $order['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 4],
        ],
    ];

    $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', $body, ['Idempotency-Key' => 'same'])
        ->assertCreated();

    $again = $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', $body, ['Idempotency-Key' => 'same']);

    $again->assertCreated();

    expect(StockLot::query()->count())->toBe(1)
        ->and(SupplierBill::query()->count())->toBe(1);

    $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $order['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ], ['Idempotency-Key' => 'same'])
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/idempotency-conflict');

    expect(StockLot::query()->count())->toBe(1)
        ->and(SupplierBill::query()->count())->toBe(1);
});

it('holds an order at the threshold and rejects self-approval', function () {
    CompanySetting::query()->update(['po_approval_threshold' => 1000]);
    [$warehouse, $product, $supplier] = procurementFixture();
    $buyer = procurementUser('Procurement');
    $manager = procurementUser('Manager');

    $large = draftOrder($buyer, $supplier, $warehouse, $product, 10);

    $this->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$large['id'].'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_approval');

    expect(StockLot::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);

    $this->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$large['id'].'/approve')
        ->assertForbidden()
        ->assertJsonPath('type', '/problems/forbidden');

    $this->actingAs($manager)
        ->postJson('/api/v1/purchase-orders/'.$large['id'].'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(StockLot::query()->count())->toBe(0);

    $small = draftOrder($buyer, $supplier, $warehouse, $product, 4);

    $this->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$small['id'].'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(StockBalance::query()->count())->toBe(0);
});

it('rejects cancelling a received order, a pinned receipt, and a consignment supplier', function () {
    [$warehouse, $product, $supplier] = procurementFixture();
    $other = Warehouse::query()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
    $buyer = procurementUser('Procurement');
    $order = submittedOrder($buyer, $supplier, $warehouse, $product, 2);

    $this->actingAs($buyer)->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $order['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ], ['Idempotency-Key' => 'all'])->assertCreated();

    $this->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$order['id'].'/cancel')
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/invalid-state');

    $pinned = procurementUser('Warehouse', $other->id);
    $second = submittedOrder($buyer, $supplier, $warehouse, $product, 2);

    $this->actingAs($pinned)->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $second['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ], ['Idempotency-Key' => 'pin'])
        ->assertForbidden()
        ->assertJsonPath('type', '/problems/forbidden');

    expect(StockLot::query()->where('goods_receipt_id', '!=', null)->count())->toBe(1);

    $consignment = Supplier::query()->create([
        'code' => 'CON',
        'name' => 'Consignment House',
        'supply_mode' => SupplyMode::Consignment,
        'status' => SupplierStatus::Active,
    ]);

    $this->actingAs($buyer)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $consignment->id,
        'destination_warehouse_id' => $warehouse->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])->assertStatus(422)->assertJsonPath('type', '/problems/invalid-state');
});

it('builds one draft per outright supplier and warehouse from alerts', function () {
    $warehouse = Warehouse::query()->create(['code' => 'DC', 'name' => 'Distribution Center', 'is_active' => true]);
    $shop = Warehouse::query()->create(['code' => 'SHOP', 'name' => 'Shop', 'is_active' => true]);
    $mill = Supplier::query()->create([
        'code' => 'MILL',
        'name' => 'Hart Mill',
        'supply_mode' => SupplyMode::Outright,
        'status' => SupplierStatus::Active,
    ]);
    $delta = Supplier::query()->create([
        'code' => 'DELTA',
        'name' => 'Delta Home',
        'supply_mode' => SupplyMode::Outright,
        'status' => SupplierStatus::Active,
    ]);
    $mug = Product::query()->create([
        'sku' => 'MUG-001',
        'name' => 'Stoneware Mug',
        'sell_price' => 150,
        'reorder_point' => 5,
        'reorder_quantity' => 8,
        'preferred_supplier_id' => $mill->id,
        'track_stock' => true,
        'is_active' => true,
    ]);
    $soap = Product::query()->create([
        'sku' => 'SOAP-001',
        'name' => 'Soap',
        'sell_price' => 100,
        'reorder_point' => 4,
        'reorder_quantity' => 0,
        'preferred_supplier_id' => $delta->id,
        'track_stock' => true,
        'is_active' => true,
    ]);
    SupplierProduct::query()->create([
        'supplier_id' => $mill->id,
        'product_id' => $mug->id,
        'agreed_unit_cost' => 70,
    ]);
    SupplierProduct::query()->create([
        'supplier_id' => $delta->id,
        'product_id' => $soap->id,
        'agreed_unit_cost' => 40,
    ]);

    $idle = Product::query()->create([
        'sku' => 'IDLE',
        'name' => 'Idle',
        'sell_price' => 10,
        'reorder_point' => 0,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $buyer = procurementUser('Procurement');

    $alerts = $this->actingAs($buyer)->getJson('/api/v1/reorder-alerts')->assertOk()->json('data');
    $skus = collect($alerts)->pluck('product.sku');

    expect($skus)->toContain('MUG-001', 'SOAP-001')
        ->and($skus)->not->toContain('IDLE');

    $created = $this->actingAs($buyer)->postJson('/api/v1/purchase-orders/from-alerts', [
        'lines' => [
            ['warehouse_id' => $warehouse->id, 'product_id' => $mug->id],
            ['warehouse_id' => $warehouse->id, 'product_id' => $soap->id],
            ['warehouse_id' => $shop->id, 'product_id' => $mug->id],
        ],
    ])->assertCreated();

    expect($created->json('data'))->toHaveCount(3);

    $orders = PurchaseOrder::query()->with('lines')->get();

    expect($orders)->toHaveCount(3)
        ->and($orders->every(fn (PurchaseOrder $order): bool => $order->status === PurchaseOrderStatus::Draft))->toBeTrue();

    $shopMug = $orders->first(
        fn (PurchaseOrder $order): bool => $order->destination_warehouse_id === $shop->id,
    );

    expect($shopMug->lines->first()->quantity_ordered)->toBe(8)
        ->and($shopMug->lines->first()->unit_cost)->toBe(70);

    $soapOrder = $orders->first(
        fn (PurchaseOrder $order): bool => $order->supplier_id === $delta->id,
    );

    expect($soapOrder->lines->first()->quantity_ordered)->toBe(4);

    $consignment = Supplier::query()->create([
        'code' => 'UNI',
        'name' => 'Unilever',
        'supply_mode' => SupplyMode::Consignment,
        'status' => SupplierStatus::Active,
    ]);
    $bar = Product::query()->create([
        'sku' => 'BAR',
        'name' => 'Bar',
        'sell_price' => 20,
        'reorder_point' => 2,
        'preferred_supplier_id' => $consignment->id,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $before = PurchaseOrder::query()->count();

    $this->actingAs($buyer)->postJson('/api/v1/purchase-orders/from-alerts', [
        'lines' => [
            ['warehouse_id' => $warehouse->id, 'product_id' => $bar->id],
        ],
    ])->assertStatus(422);

    expect(PurchaseOrder::query()->count())->toBe($before)
        ->and(SupplierBill::query()->where('status', SupplierBillStatus::Open)->count())->toBe(0);
});

function procurementFixture(): array
{
    $warehouse = Warehouse::query()->create([
        'code' => 'DC',
        'name' => 'Distribution Center',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'sku' => 'MUG-001',
        'name' => 'Stoneware Mug',
        'sell_price' => 150,
        'track_stock' => true,
        'is_active' => true,
    ]);
    $supplier = Supplier::query()->create([
        'code' => 'MILL',
        'name' => 'Hart Mill',
        'supply_mode' => SupplyMode::Outright,
        'payment_terms_days' => 30,
        'status' => SupplierStatus::Active,
    ]);
    SupplierProduct::query()->create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'agreed_unit_cost' => 100,
    ]);

    return [$warehouse, $product, $supplier];
}

function draftOrder(User $buyer, Supplier $supplier, Warehouse $warehouse, Product $product, int $quantity): array
{
    return test()->actingAs($buyer)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => $quantity],
        ],
    ])->assertCreated()->json('data');
}

function submittedOrder(User $buyer, Supplier $supplier, Warehouse $warehouse, Product $product, int $quantity): array
{
    $order = draftOrder($buyer, $supplier, $warehouse, $product, $quantity);

    test()->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$order['id'].'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    return $order;
}

function procurementUser(string $role, ?string $warehouseId = null): User
{
    $user = User::factory()->create(['warehouse_id' => $warehouseId]);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    return $user;
}
