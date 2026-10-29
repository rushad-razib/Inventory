<?php

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\LotOwnership;
use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierReturnStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Models\CashEntry;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerPointEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
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
        'adjustment_approval_threshold' => 5000,
        'points_earn_per' => 100,
        'point_redeem_value' => 1,
    ]);
});

it('rejects sale routes without authentication or permission', function () {
    $this->getJson('/api/v1/sales')->assertUnauthorized();

    $warehouseUser = posUser('Warehouse');

    $this->actingAs($warehouseUser)->getJson('/api/v1/sales')->assertForbidden();
    $this->actingAs($warehouseUser)->postJson('/api/v1/sales', [])->assertForbidden();
    $this->actingAs($warehouseUser)->getJson('/api/v1/customer-returns')->assertForbidden();
    $this->actingAs($warehouseUser)->getJson('/api/v1/approvals')->assertForbidden();
});

it('stores the soap and mug split and refunds only the mug', function () {
    $warehouse = posWarehouse('STORE');
    $soap = posProduct('SOAP', 100);
    $mug = posProduct('MUG', 150);
    $unilever = posSupplier('UNILEVER', SupplyMode::Consignment);
    $soapLot = posLot($warehouse, $soap, $unilever, LotOwnership::Consignment, 60, 5, now()->subDay());
    $mugLot = posLot($warehouse, $mug, null, LotOwnership::Owned, 70, 5, now());
    $customer = posCustomer('+8801711000001', 30);
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'soap-mug'])
        ->postJson('/api/v1/sales', [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'lines' => [
                ['product_id' => $soap->id, 'quantity' => 1],
                ['product_id' => $mug->id, 'quantity' => 1],
            ],
            'tenders' => [
                ['type' => 'points', 'amount' => 30],
                ['type' => 'cash', 'amount' => 220],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.points_earned', 2)
        ->assertJsonPath('data.points_redeemed', 30)
        ->assertJsonPath('data.points_balance_after', 2)
        ->json('data');

    $lines = collect($sale['lines'])->keyBy(fn (array $line) => $line['product']['sku']);
    expect($lines['SOAP']['cash_paid'])->toBe(88)
        ->and($lines['SOAP']['points_redeemed'])->toBe(12)
        ->and($lines['SOAP']['points_earned'])->toBe(1)
        ->and($lines['MUG']['cash_paid'])->toBe(132)
        ->and($lines['MUG']['points_redeemed'])->toBe(18)
        ->and($lines['MUG']['points_earned'])->toBe(1)
        ->and($lines['SOAP']['allocations'][0]['ownership'])->toBe('consignment')
        ->and($lines['SOAP']['allocations'][0]['payable']['amount'])->toBe(60)
        ->and($lines['MUG']['allocations'][0]['payable'])->toBeNull();

    $return = $this->actingAs($cashier)->postJson('/api/v1/customer-returns', [
        'sale_id' => $sale['id'],
        'lines' => [
            ['sale_line_id' => $lines['MUG']['id'], 'quantity' => 1, 'reason' => 'resaleable'],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval')
        ->assertJsonPath('data.cash_amount', 132)
        ->assertJsonPath('data.points_restored', 18)
        ->assertJsonPath('data.points_clawed_back', 1)
        ->json('data');

    expect($return['lines'])->toHaveCount(1);

    $this->actingAs($cashier)
        ->postJson('/api/v1/customer-returns/'.$return['id'].'/approve')
        ->assertForbidden()
        ->assertJsonPath('type', '/problems/forbidden');

    expect(CashEntry::query()->where('type', CashEntryType::CustomerRefundOut)->count())->toBe(0);
    expect(StockLot::query()->find($mugLot->id)->qty_on_hand)->toBe(4);

    $this->actingAs($manager)
        ->postJson('/api/v1/customer-returns/'.$return['id'].'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(CashEntry::query()->where('type', CashEntryType::CustomerRefundOut)->count())->toBe(0);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'refund-mug'])
        ->postJson('/api/v1/customer-returns/'.$return['id'].'/refund')
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.cash_amount', 132);

    expect(StockLot::query()->find($mugLot->id)->qty_on_hand)->toBe(5)
        ->and(StockLot::query()->find($soapLot->id)->qty_on_hand)->toBe(4)
        ->and($customer->fresh()->points_balance)->toBe(19)
        ->and(CashEntry::query()->where('type', CashEntryType::CustomerRefundOut)->where('location', CashLocation::Till)->value('amount'))->toBe(132)
        ->and(Sale::query()->find($sale['id'])->lines()->where('product_id', $soap->id)->value('quantity_returned'))->toBe(0);
});

it('earns on cash and card only and skips points for a walk-in', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 220);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $customer = posCustomer('+8801711000002', 0);
    $cashier = posUser('Cashier');

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'earn-220'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, $customer, 1, [
            ['type' => 'cash', 'amount' => 220],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.points_earned', 2);

    $small = posProduct('SOAP', 99);
    posLot($warehouse, $small, null, LotOwnership::Owned, 40, 5, now());

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'earn-99'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $small, $customer, 1, [
            ['type' => 'cash', 'amount' => 99],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.points_earned', 0);

    $walk = posProduct('BOWL', 100);
    posLot($warehouse, $walk, null, LotOwnership::Owned, 20, 5, now());

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'walk-in'])
        ->postJson('/api/v1/sales', [
            'warehouse_id' => $warehouse->id,
            'customer_id' => null,
            'lines' => [['product_id' => $walk->id, 'quantity' => 1]],
            'tenders' => [['type' => 'cash', 'amount' => 100]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.customer_id', null)
        ->assertJsonPath('data.points_earned', 0);

    expect(CustomerPointEntry::query()->where('points', '>', 0)->count())->toBe(1);
});

it('rejects too many points and replays a sale without a second movement', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $customer = posCustomer('+8801711000001', 10);
    $cashier = posUser('Cashier');
    $body = posSaleBody($warehouse, $product, $customer, 1, [
        ['type' => 'points', 'amount' => 30],
        ['type' => 'cash', 'amount' => 120],
    ]);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'too-many'])
        ->postJson('/api/v1/sales', $body)
        ->assertStatus(422)
        ->assertJsonPath('type', '/problems/invalid-state');

    $overTotal = posSaleBody($warehouse, $product, $customer, 1, [
        ['type' => 'points', 'amount' => 10],
        ['type' => 'cash', 'amount' => 200],
    ]);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'over-total'])
        ->postJson('/api/v1/sales', $overTotal)
        ->assertStatus(422);

    $ok = posSaleBody($warehouse, $product, $customer, 1, [
        ['type' => 'points', 'amount' => 10],
        ['type' => 'cash', 'amount' => 140],
    ]);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'once'])
        ->postJson('/api/v1/sales', $ok)
        ->assertCreated();

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'once'])
        ->postJson('/api/v1/sales', $ok)
        ->assertCreated();

    expect(Sale::query()->count())->toBe(1)
        ->and(StockMovement::query()->where('type', MovementType::Sale)->count())->toBe(1);

    $changed = posSaleBody($warehouse, $product, $customer, 2, [
        ['type' => 'cash', 'amount' => 300],
    ]);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'once'])
        ->postJson('/api/v1/sales', $changed)
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/idempotency-conflict');
});

it('consumes an owned lot before an older consignment lot and rejects a shortage', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    $supplier = posSupplier('UNI', SupplyMode::Consignment);
    $consignment = posLot($warehouse, $product, $supplier, LotOwnership::Consignment, 60, 4, now()->subDays(2));
    $owned = posLot($warehouse, $product, null, LotOwnership::Owned, 70, 1, now());
    $cashier = posUser('Cashier');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'owned-first'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 150],
        ]))
        ->assertCreated()
        ->json('data');

    expect($sale['lines'][0]['allocations'][0]['stock_lot_id'])->toBe($owned->id)
        ->and($sale['lines'][0]['allocations'][0]['ownership'])->toBe('owned')
        ->and(SupplierBill::query()->count())->toBe(0)
        ->and(StockLot::query()->find($consignment->id)->qty_on_hand)->toBe(4);

    $empty = posProduct('BOWL', 40);
    StockBalance::query()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $empty->id,
        'on_hand' => 0,
        'damaged' => 0,
        'in_transit' => 0,
    ]);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'short'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $empty, null, 1, [
            ['type' => 'cash', 'amount' => 40],
        ]))
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/insufficient-stock');

    expect(StockMovement::query()->where('product_id', $empty->id)->count())->toBe(0);
});

it('creates a consignment payable at lot cost even when points reduce the shelf', function () {
    $warehouse = posWarehouse('STORE');
    $soap = posProduct('SOAP', 100);
    $supplier = posSupplier('UNI', SupplyMode::Consignment);
    posLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 3, now());
    $customer = posCustomer('+8801711000001', 30);
    $cashier = posUser('Cashier');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'payable'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $soap, $customer, 1, [
            ['type' => 'points', 'amount' => 30],
            ['type' => 'cash', 'amount' => 70],
        ]))
        ->assertCreated()
        ->json('data');

    expect($sale['lines'][0]['allocations'][0]['payable']['amount'])->toBe(60)
        ->and($sale['lines'][0]['allocations'][0]['payable']['credited_amount'])->toBe(0)
        ->and(SupplierBill::query()->count())->toBe(1);
});

it('keeps two partial refunds inside the stored line', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 101);
    $lot = posLot($warehouse, $product, null, LotOwnership::Owned, 40, 2, now());
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'partial'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 2, [
            ['type' => 'cash', 'amount' => 202],
        ]))
        ->assertCreated()
        ->json('data');

    $lineId = $sale['lines'][0]['id'];
    $first = posReturn($cashier, $sale['id'], $lineId, 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$first.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'partial-1'])
        ->postJson('/api/v1/customer-returns/'.$first.'/refund')
        ->assertOk()
        ->assertJsonPath('data.cash_amount', 101);

    $second = posReturn($cashier, $sale['id'], $lineId, 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$second.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'partial-2'])
        ->postJson('/api/v1/customer-returns/'.$second.'/refund')
        ->assertOk()
        ->assertJsonPath('data.cash_amount', 101);

    expect(CashEntry::query()->where('type', CashEntryType::CustomerRefundOut)->sum('amount'))->toBe(202)
        ->and(StockLot::query()->find($lot->id)->qty_on_hand)->toBe(2);
});

it('writes nothing on reject and pays only after approval', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    $lot = posLot($warehouse, $product, null, LotOwnership::Owned, 70, 2, now());
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');
    $sale = postedSale($cashier, $warehouse, $product, null, 1, [['type' => 'cash', 'amount' => 150]]);
    $movements = StockMovement::query()->count();
    $returnId = posReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 1, 'resaleable');

    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/reject')->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(StockMovement::query()->count())->toBe($movements)
        ->and(CashEntry::query()->where('type', CashEntryType::CustomerRefundOut)->count())->toBe(0)
        ->and(CustomerPointEntry::query()->count())->toBe(0)
        ->and(StockLot::query()->find($lot->id)->qty_on_hand)->toBe(1);

    $again = posReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 1);
    $this->actingAs($cashier)->getJson('/api/v1/approvals')->assertForbidden();
    $inbox = $this->actingAs($manager)->getJson('/api/v1/approvals')->assertOk()->json('data.customer_returns');
    expect(collect($inbox)->pluck('id'))->toContain($again);
});

it('credits an open consignment payable and opens a draft supplier return for damage', function () {
    $warehouse = posWarehouse('STORE');
    $soap = posProduct('SOAP', 100);
    $mug = posProduct('MUG', 150);
    $supplier = posSupplier('UNI', SupplyMode::Consignment);
    $soapLot = posLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 1, now());
    $mugLot = posLot($warehouse, $mug, null, LotOwnership::Owned, 70, 1, now());
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $soapSale = postedSale($cashier, $warehouse, $soap, null, 1, [['type' => 'cash', 'amount' => 100]], 'consign-sale');
    $soapReturn = posReturn($cashier, $soapSale['id'], $soapSale['lines'][0]['id'], 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$soapReturn.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'credit'])
        ->postJson('/api/v1/customer-returns/'.$soapReturn.'/refund')
        ->assertOk();

    $bill = SupplierBill::query()->first();
    expect($bill->credited_amount)->toBe(60)
        ->and($bill->status)->toBe(SupplierBillStatus::Credited)
        ->and(StockLot::query()->find($soapLot->id)->qty_on_hand)->toBe(1);

    $mugSale = postedSale($cashier, $warehouse, $mug, null, 1, [['type' => 'card', 'amount' => 150]], 'card-sale');
    $damaged = posReturn($cashier, $mugSale['id'], $mugSale['lines'][0]['id'], 1, 'damaged');
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$damaged.'/approve')->assertOk();
    $refund = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'damage'])
        ->postJson('/api/v1/customer-returns/'.$damaged.'/refund')
        ->assertOk()
        ->json('data');

    expect($refund['supplier_returns'])->toHaveCount(1)
        ->and($refund['supplier_returns'][0]['status'])->toBe('draft')
        ->and($refund['supplier_returns'][0]['unit_cost'])->toBe(70)
        ->and(StockLot::query()->find($mugLot->id)->qty_on_hand)->toBe(0)
        ->and(StockLot::query()->find($mugLot->id)->qty_damaged)->toBe(1)
        ->and(SupplierReturn::query()->where('status', SupplierReturnStatus::Draft)->count())->toBe(1)
        ->and(CashEntry::query()->where('source_id', $damaged)->value('amount'))->toBe(150)
        ->and(CashEntry::query()->where('type', CashEntryType::SaleCardIn)->where('location', CashLocation::Bank)->count())->toBe(1);
});

it('allows a clawback below zero and then refuses another redemption', function () {
    $warehouse = posWarehouse('STORE');
    $first = posProduct('MUG', 220);
    $second = posProduct('SOAP', 100);
    posLot($warehouse, $first, null, LotOwnership::Owned, 70, 1, now());
    posLot($warehouse, $second, null, LotOwnership::Owned, 40, 1, now());
    $customer = posCustomer('+8801711000003', 0);
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $earned = postedSale($cashier, $warehouse, $first, $customer, 1, [['type' => 'cash', 'amount' => 220]], 'earn');
    expect($customer->fresh()->points_balance)->toBe(2);

    postedSale($cashier, $warehouse, $second, $customer, 1, [
        ['type' => 'points', 'amount' => 2],
        ['type' => 'cash', 'amount' => 98],
    ], 'spend');
    expect($customer->fresh()->points_balance)->toBe(0);

    $returnId = posReturn($cashier, $earned['id'], $earned['lines'][0]['id'], 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'claw'])
        ->postJson('/api/v1/customer-returns/'.$returnId.'/refund')
        ->assertOk();

    expect($customer->fresh()->points_balance)->toBe(-2);

    $third = posProduct('BOWL', 50);
    posLot($warehouse, $third, null, LotOwnership::Owned, 10, 1, now());

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'wait'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $third, $customer, 1, [
            ['type' => 'points', 'amount' => 1],
            ['type' => 'cash', 'amount' => 49],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('type', '/problems/invalid-state');
});

it('voids a sale with no return and refuses a void after any return', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    $supplier = posSupplier('UNI', SupplyMode::Consignment);
    $lot = posLot($warehouse, $product, $supplier, LotOwnership::Consignment, 60, 2, now());
    $customer = posCustomer('+8801711000004', 10);
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $sale = postedSale($cashier, $warehouse, $product, $customer, 1, [
        ['type' => 'points', 'amount' => 10],
        ['type' => 'cash', 'amount' => 140],
    ], 'void-me');

    $returnId = posReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/reject')->assertOk();

    $this->actingAs($manager)->postJson('/api/v1/sales/'.$sale['id'].'/void')
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/invalid-state');

    $other = postedSale($cashier, $warehouse, $product, $customer, 1, [
        ['type' => 'cash', 'amount' => 150],
    ], 'void-ok');

    $this->actingAs($cashier)->postJson('/api/v1/sales/'.$other['id'].'/void')->assertForbidden();

    $this->actingAs($manager)->postJson('/api/v1/sales/'.$other['id'].'/void')
        ->assertOk()
        ->assertJsonPath('data.status', 'voided');

    expect(StockLot::query()->find($lot->id)->qty_on_hand)->toBe(1)
        ->and(Sale::query()->find($other['id'])->status)->toBe(SaleStatus::Voided)
        ->and(SupplierBill::query()->where('status', SupplierBillStatus::Credited)->count())->toBe(1)
        ->and(CashEntry::query()->where('source_id', $other['id'])->where('direction', CashDirection::Out)->count())->toBe(1)
        ->and($customer->fresh()->points_balance)->toBe(1);
});

it('keeps a pinned cashier inside their warehouse', function () {
    $store = posWarehouse('STORE');
    $dc = posWarehouse('DC');
    $product = posProduct('MUG', 150);
    posLot($store, $product, null, LotOwnership::Owned, 70, 2, now());
    posLot($dc, $product, null, LotOwnership::Owned, 70, 2, now());
    $pinned = posUser('Cashier', $store->id);
    $manager = posUser('Manager');

    $this->actingAs($pinned)->withHeaders(['Idempotency-Key' => 'other-wh'])
        ->postJson('/api/v1/sales', posSaleBody($dc, $product, null, 1, [
            ['type' => 'cash', 'amount' => 150],
        ]))
        ->assertForbidden()
        ->assertJsonPath('type', '/problems/forbidden');

    $sale = postedSale($pinned, $store, $product, null, 1, [['type' => 'cash', 'amount' => 150]], 'home');
    $returnId = posReturn($pinned, $sale['id'], $sale['lines'][0]['id'], 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();

    $pinned->warehouse_id = $dc->id;
    $pinned->save();

    $this->actingAs($pinned)->withHeaders(['Idempotency-Key' => 'wrong-refund'])
        ->postJson('/api/v1/customer-returns/'.$returnId.'/refund')
        ->assertForbidden();
});

function posWarehouse(string $code): Warehouse
{
    return Warehouse::query()->create([
        'code' => $code,
        'name' => $code,
        'is_active' => true,
    ]);
}

function posProduct(string $sku, int $price): Product
{
    return Product::query()->create([
        'sku' => $sku,
        'name' => $sku,
        'sell_price' => $price,
        'track_stock' => true,
        'is_active' => true,
    ]);
}

function posSupplier(string $code, SupplyMode $mode): Supplier
{
    return Supplier::query()->create([
        'code' => $code,
        'name' => $code,
        'supply_mode' => $mode,
        'payment_terms_days' => 30,
        'status' => SupplierStatus::Active,
    ]);
}

function posLot(
    Warehouse $warehouse,
    Product $product,
    ?Supplier $supplier,
    LotOwnership $ownership,
    int $cost,
    int $quantity,
    mixed $receivedAt,
): StockLot {
    $lot = StockLot::query()->create([
        'supplier_id' => $supplier?->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'unit_cost' => $cost,
        'ownership' => $ownership,
        'qty_received' => $quantity,
        'qty_on_hand' => $quantity,
        'qty_damaged' => 0,
        'received_at' => $receivedAt,
    ]);

    $balance = StockBalance::query()->firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
        ['on_hand' => 0, 'damaged' => 0, 'in_transit' => 0],
    );
    $balance->on_hand += $quantity;
    $balance->save();

    return $lot;
}

function posCustomer(string $mobile, int $points): Customer
{
    return Customer::query()->create([
        'mobile' => $mobile,
        'points_balance' => $points,
    ]);
}

function posUser(string $role, ?string $warehouseId = null): User
{
    $user = User::factory()->create(['warehouse_id' => $warehouseId]);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    return $user;
}

/**
 * @param  list<array{type: string, amount: int}>  $tenders
 * @return array<string, mixed>
 */
function posSaleBody(Warehouse $warehouse, Product $product, ?Customer $customer, int $quantity, array $tenders): array
{
    return [
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer?->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => $quantity],
        ],
        'tenders' => $tenders,
    ];
}

/**
 * @param  list<array{type: string, amount: int}>  $tenders
 * @return array<string, mixed>
 */
function postedSale(
    User $cashier,
    Warehouse $warehouse,
    Product $product,
    ?Customer $customer,
    int $quantity,
    array $tenders,
    string $key = 'sale',
): array {
    return test()->actingAs($cashier)->withHeaders(['Idempotency-Key' => $key])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, $customer, $quantity, $tenders))
        ->assertCreated()
        ->json('data');
}

function posReturn(User $cashier, string $saleId, string $lineId, int $quantity, string $reason = 'resaleable'): string
{
    return test()->actingAs($cashier)->postJson('/api/v1/customer-returns', [
        'sale_id' => $saleId,
        'lines' => [
            ['sale_line_id' => $lineId, 'quantity' => $quantity, 'reason' => $reason],
        ],
    ])->assertCreated()->json('data.id');
}
