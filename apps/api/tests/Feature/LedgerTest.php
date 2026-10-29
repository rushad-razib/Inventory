<?php

use App\Enums\LotOwnership;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\SupplierReturn;
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
        'adjustment_approval_threshold' => 5,
        'points_earn_per' => 100,
        'point_redeem_value' => 1,
    ]);
});

it('rejects stock routes without authentication or permission', function () {
    $this->getJson('/api/v1/stock-balances')->assertUnauthorized();

    $cashier = User::factory()->create();
    $cashier->assignRole('Cashier');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actingAs($cashier)->getJson('/api/v1/stock-balances')->assertOk();
    $this->actingAs($cashier)->getJson('/api/v1/adjustments')->assertForbidden();
    $this->actingAs($cashier)->postJson('/api/v1/transfers', [])->assertForbidden();
});

it('rejects a second decrement that would oversell', function () {
    [$warehouse, $product] = stockFixture();
    putLot($warehouse, $product, 4);

    $user = stockUser('Warehouse', $warehouse->id);

    $first = $this->actingAs($user)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => -4,
        'reason' => 'correction',
    ], ['Idempotency-Key' => 'down-1']);

    $first->assertCreated()->assertJsonPath('data.status', 'posted');

    $this->actingAs($user)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => -4,
        'reason' => 'correction',
    ], ['Idempotency-Key' => 'down-2'])
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/insufficient-stock');

    expect(StockMovement::query()->count())->toBe(1)
        ->and(StockBalance::query()->first()->on_hand)->toBe(0);
});

it('consumes owned lots before older consignment lots', function () {
    [$warehouse, $product] = stockFixture();
    $consignment = putLot($warehouse, $product, 5, LotOwnership::Consignment, now()->subDay());
    $owned = putLot($warehouse, $product, 1, LotOwnership::Owned, now());
    StockBalance::query()->where('product_id', $product->id)->update(['on_hand' => 6]);

    $user = stockUser('Warehouse', $warehouse->id);

    $this->actingAs($user)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => -2,
        'reason' => 'count',
    ], ['Idempotency-Key' => 'fifo-1'])->assertCreated();

    expect($owned->refresh()->qty_on_hand)->toBe(0)
        ->and($consignment->refresh()->qty_on_hand)->toBe(4);
});

it('posts a small adjustment and waits when the quantity meets the threshold', function () {
    [$warehouse, $product] = stockFixture();
    $requester = stockUser('Warehouse', $warehouse->id);
    $manager = stockUser('Manager');

    $this->actingAs($requester)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => 4,
        'reason' => 'count',
    ], ['Idempotency-Key' => 'small'])->assertCreated()
        ->assertJsonPath('data.status', 'posted');

    expect(StockBalance::query()->first()->on_hand)->toBe(4)
        ->and(AuditLog::query()->where('action', 'adjustment.posted')->count())->toBe(1);

    $pending = $this->actingAs($requester)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'reason' => 'count',
    ], ['Idempotency-Key' => 'large'])->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');

    expect(StockBalance::query()->first()->on_hand)->toBe(4);

    $this->actingAs($requester)
        ->postJson('/api/v1/adjustments/'.$pending->json('data.id').'/approve')
        ->assertForbidden()
        ->assertJsonPath('type', '/problems/forbidden');

    expect(StockBalance::query()->first()->on_hand)->toBe(4);

    $this->actingAs($manager)
        ->postJson('/api/v1/adjustments/'.$pending->json('data.id').'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'posted');

    expect(StockBalance::query()->first()->on_hand)->toBe(9)
        ->and(AuditLog::query()->where('action', 'adjustment.approved')->count())->toBe(1);
});

it('moves damaged units off the shelf without a supplier return', function () {
    [$warehouse, $product] = stockFixture();
    putLot($warehouse, $product, 10);
    $user = stockUser('Warehouse', $warehouse->id);

    $this->actingAs($user)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'reason' => 'damage',
    ], ['Idempotency-Key' => 'damage-1'])->assertCreated();

    $balance = StockBalance::query()->first();
    $lot = StockLot::query()->first();

    expect($balance->on_hand)->toBe(7)
        ->and($balance->damaged)->toBe(3)
        ->and($lot->qty_on_hand)->toBe(7)
        ->and($lot->qty_damaged)->toBe(3)
        ->and(SupplierReturn::query()->count())->toBe(0);
});

it('does not double a lot when a transfer receive is replayed', function () {
    [$source, $product] = stockFixture();
    $destination = Warehouse::query()->create([
        'code' => 'SHOP',
        'name' => 'Shop',
        'is_active' => true,
    ]);
    $lot = putLot($source, $product, 4, LotOwnership::Owned, now(), 70, supplierId: null);
    $user = stockUser('Admin');

    $transfer = $this->actingAs($user)->postJson('/api/v1/transfers', [
        'source_warehouse_id' => $source->id,
        'destination_warehouse_id' => $destination->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 4],
        ],
    ])->assertCreated()->json('data');

    $this->actingAs($user)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/ship',
        [],
        ['Idempotency-Key' => 'ship-1'],
    )->assertOk()->assertJsonPath('data.status', 'in_transit');

    $receive = $this->actingAs($user)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/receive',
        ['note' => 'a'],
        ['Idempotency-Key' => 'receive-1'],
    );

    $receive->assertOk()->assertJsonPath('data.status', 'received');

    $again = $this->actingAs($user)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/receive',
        ['note' => 'a'],
        ['Idempotency-Key' => 'receive-1'],
    );

    $again->assertOk()->assertJsonPath('data.id', $transfer['id']);

    expect(StockLot::query()->where('warehouse_id', $destination->id)->count())->toBe(1);

    $destinationLot = StockLot::query()->where('warehouse_id', $destination->id)->first();

    expect($destinationLot->unit_cost)->toBe(70)
        ->and($destinationLot->ownership)->toBe(LotOwnership::Owned)
        ->and($destinationLot->qty_on_hand)->toBe(4)
        ->and($lot->refresh()->qty_on_hand)->toBe(0)
        ->and(StockBalance::query()->where('warehouse_id', $source->id)->first()->in_transit)->toBe(0)
        ->and(StockBalance::query()->where('warehouse_id', $destination->id)->first()->on_hand)->toBe(4);

    $this->actingAs($user)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/receive',
        ['note' => 'b'],
        ['Idempotency-Key' => 'receive-1'],
    )->assertStatus(409)->assertJsonPath('type', '/problems/idempotency-conflict');

    expect(StockLot::query()->where('warehouse_id', $destination->id)->count())->toBe(1);
});

it('rejects cancelling a shipped transfer and shipping from another warehouse', function () {
    [$source, $product] = stockFixture();
    $other = Warehouse::query()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
    $destination = Warehouse::query()->create(['code' => 'DEST', 'name' => 'Dest', 'is_active' => true]);
    putLot($source, $product, 2);
    $shipper = stockUser('Admin');

    $transfer = $this->actingAs($shipper)->postJson('/api/v1/transfers', [
        'source_warehouse_id' => $source->id,
        'destination_warehouse_id' => $destination->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ])->assertCreated()->json('data');

    $pinned = stockUser('Warehouse', $other->id);

    $this->actingAs($pinned)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/ship',
        [],
        ['Idempotency-Key' => 'pin-ship'],
    )->assertForbidden();

    $this->actingAs($shipper)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/ship',
        [],
        ['Idempotency-Key' => 'ship-ok'],
    )->assertOk();

    $this->actingAs($shipper)->postJson(
        '/api/v1/transfers/'.$transfer['id'].'/cancel',
        [],
        ['Idempotency-Key' => 'cancel-late'],
    )->assertStatus(409)->assertJsonPath('type', '/problems/invalid-state');
});

function stockFixture(): array
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

    return [$warehouse, $product];
}

function putLot(
    Warehouse $warehouse,
    Product $product,
    int $quantity,
    LotOwnership $ownership = LotOwnership::Owned,
    ?DateTimeInterface $receivedAt = null,
    int $unitCost = 40,
    ?string $supplierId = null,
): StockLot {
    $lot = StockLot::query()->create([
        'supplier_id' => $supplierId,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'unit_cost' => $unitCost,
        'ownership' => $ownership,
        'qty_received' => $quantity,
        'qty_on_hand' => $quantity,
        'qty_damaged' => 0,
        'received_at' => $receivedAt ?? now(),
    ]);

    $balance = StockBalance::query()->firstOrCreate(
        [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ],
        ['on_hand' => 0, 'damaged' => 0, 'in_transit' => 0],
    );

    $balance->on_hand += $quantity;
    $balance->save();

    return $lot;
}

function stockUser(string $role, ?string $warehouseId = null): User
{
    $user = User::factory()->create(['warehouse_id' => $warehouseId]);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    return $user;
}
