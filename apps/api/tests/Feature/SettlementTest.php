<?php

use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\LotOwnership;
use App\Enums\MovementType;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierReturnStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Models\AuditLog;
use App\Models\CashEntry;
use App\Models\CompanySetting;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierCredit;
use App\Models\SupplierProduct;
use App\Models\SupplierRefundReceivable;
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

it('rejects settlement routes without authentication or permission', function () {
    $this->getJson('/api/v1/supplier-returns')->assertUnauthorized();
    $this->postJson('/api/v1/supplier-settlements', [])->assertUnauthorized();
    $this->getJson('/api/v1/cash-balances')->assertUnauthorized();

    $warehouse = settlementUser('Warehouse');
    $manager = settlementUser('Manager');

    $this->actingAs($warehouse)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [],
    ], ['Idempotency-Key' => 'no-settle'])->assertForbidden();
    $this->actingAs($warehouse)->postJson('/api/v1/supplier-refunds', [
        'supplier_return_id' => '00000000-0000-0000-0000-000000000000',
    ], ['Idempotency-Key' => 'no-refund'])->assertForbidden();

    $this->actingAs($manager)->getJson('/api/v1/supplier-bills')->assertOk();
    $this->actingAs($manager)->getJson('/api/v1/cash-balances')->assertOk();
    $this->actingAs($manager)->getJson('/api/v1/cash-entries')->assertOk();
    $this->actingAs($manager)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [],
    ], ['Idempotency-Key' => 'manager-settle'])->assertForbidden();
});

it('pays an outright bill from the bank once', function () {
    $buyer = settlementUser('Procurement');
    $received = receiveOutright($buyer, 'MILL', 'MUG', 70, 1);
    $billId = $received['bill_id'];

    $paid = $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$billId],
    ], ['Idempotency-Key' => 'pay-mill'])->assertCreated()->json('data');

    expect($paid['amount'])->toBe(70)
        ->and($paid['cash_entry_id'])->not->toBeNull()
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->count())->toBe(1)
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->value('amount'))->toBe(70)
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->value('location'))->toBe(CashLocation::Bank)
        ->and(SupplierBill::query()->find($billId)->status)->toBe(SupplierBillStatus::Settled)
        ->and(SupplierBill::query()->find($billId)->amount_paid)->toBe(70)
        ->and(AuditLog::query()->where('action', 'supplier_settlement.posted')->count())->toBe(1);

    $again = $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$billId],
    ], ['Idempotency-Key' => 'pay-mill'])->assertCreated()->json('data');

    expect($again['id'])->toBe($paid['id'])
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->count())->toBe(1);
});

it('rejects a second settlement body for the same key and bills from two suppliers', function () {
    $buyer = settlementUser('Procurement');
    $first = receiveOutright($buyer, 'MILL', 'MUG', 70, 1);
    $second = receiveOutright($buyer, 'MILL', 'BOWL', 40, 1);
    $other = receiveOutright($buyer, 'DELTA', 'SOAP', 20, 1);

    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$first['bill_id']],
    ], ['Idempotency-Key' => 'once'])->assertCreated();

    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$first['bill_id'], $second['bill_id']],
    ], ['Idempotency-Key' => 'once'])
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/idempotency-conflict');

    expect(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->count())->toBe(1)
        ->and(SupplierBill::query()->find($second['bill_id'])->status)->toBe(SupplierBillStatus::Open);

    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$second['bill_id'], $other['bill_id']],
    ], ['Idempotency-Key' => 'two-suppliers'])
        ->assertStatus(409)
        ->assertJsonPath('type', '/problems/invalid-state');

    expect(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->count())->toBe(1);
});

it('settles consignment payables in one bank outflow without reducing the point discount', function () {
    $buyer = settlementUser('Procurement');
    $cashier = settlementUser('Cashier');
    $warehouse = settlementWarehouse('STORE');
    $supplier = settlementSupplier('UNILEVER', SupplyMode::Consignment);
    $soap = settlementProduct('SOAP', 100);
    $mug = settlementProduct('MUG', 100);
    settlementLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 5);
    settlementLot($warehouse, $mug, $supplier, LotOwnership::Consignment, 60, 5);
    $customer = settlementCustomer('+8801711000099', 30);

    $discounted = settlementSale($cashier, $warehouse, $soap, $customer, [
        ['type' => 'points', 'amount' => 30],
        ['type' => 'cash', 'amount' => 70],
    ], 'points-sale');
    $plain = settlementSale($cashier, $warehouse, $mug, null, [
        ['type' => 'cash', 'amount' => 100],
    ], 'cash-sale');

    expect($discounted['lines'][0]['allocations'][0]['payable']['amount'])->toBe(60)
        ->and($plain['lines'][0]['allocations'][0]['payable']['amount'])->toBe(60);

    $paid = $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [
            $discounted['lines'][0]['allocations'][0]['payable']['id'],
            $plain['lines'][0]['allocations'][0]['payable']['id'],
        ],
    ], ['Idempotency-Key' => 'settle-uni'])->assertCreated()->json('data');

    expect($paid['amount'])->toBe(120)
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->count())->toBe(1)
        ->and(SupplierBill::query()->where('status', SupplierBillStatus::Settled)->count())->toBe(2);
});

it('applies a settled consignment credit to the next settlement', function () {
    $buyer = settlementUser('Procurement');
    $cashier = settlementUser('Cashier');
    $manager = settlementUser('Manager');
    $warehouse = settlementWarehouse('STORE');
    $supplier = settlementSupplier('UNILEVER', SupplyMode::Consignment);
    $soap = settlementProduct('SOAP', 100);
    settlementLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 5);
    $customer = settlementCustomer('+8801711000088', 0);

    $first = settlementSale($cashier, $warehouse, $soap, $customer, [
        ['type' => 'cash', 'amount' => 100],
    ], 'sale-1');
    $firstBill = $first['lines'][0]['allocations'][0]['payable']['id'];

    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$firstBill],
    ], ['Idempotency-Key' => 'pay-first'])->assertCreated()->assertJsonPath('data.amount', 60);

    $returnId = settlementReturn($cashier, $first['id'], $first['lines'][0]['id'], 'resaleable');
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->postJson('/api/v1/customer-returns/'.$returnId.'/refund', [], [
        'Idempotency-Key' => 'refund-resale',
    ])->assertOk();

    expect(SupplierCredit::query()->where('supplier_id', $supplier->id)->value('amount'))->toBe(60)
        ->and(SupplierCredit::query()->value('amount_applied'))->toBe(0);

    $second = settlementSale($cashier, $warehouse, $soap, $customer, [
        ['type' => 'cash', 'amount' => 100],
    ], 'sale-2');
    $secondBill = $second['lines'][0]['allocations'][0]['payable']['id'];

    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$secondBill],
    ], ['Idempotency-Key' => 'pay-second'])->assertCreated()->assertJsonPath('data.amount', 0);

    expect(CashEntry::query()->where('type', CashEntryType::SupplierPaymentOut)->count())->toBe(1)
        ->and(SupplierBill::query()->find($secondBill)->status)->toBe(SupplierBillStatus::Settled)
        ->and(SupplierBill::query()->find($secondBill)->amount_paid)->toBe(60)
        ->and(SupplierCredit::query()->value('amount_applied'))->toBe(60);
});

it('removes unsold consignment damage with no cash', function () {
    $buyer = settlementUser('Procurement');
    $warehouseUser = settlementUser('Warehouse');
    $warehouse = settlementWarehouse('STORE');
    $supplier = settlementSupplier('UNILEVER', SupplyMode::Consignment);
    $soap = settlementProduct('SOAP', 100);
    $lot = settlementLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 4);

    $this->actingAs($warehouseUser)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $soap->id,
        'quantity' => 1,
        'reason' => 'damage',
    ], ['Idempotency-Key' => 'damage-unsold'])->assertCreated();

    $opened = $this->actingAs($warehouseUser)->postJson('/api/v1/supplier-returns', [
        'stock_lot_id' => $lot->id,
        'quantity' => 1,
    ])->assertCreated()->json('data');

    expect($opened['customer_return_id'])->toBeNull()
        ->and($opened['status'])->toBe('draft');

    $this->actingAs($warehouseUser)
        ->postJson('/api/v1/supplier-returns/'.$opened['id'].'/ship')
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped');

    $this->actingAs($warehouseUser)
        ->postJson('/api/v1/supplier-returns/'.$opened['id'].'/accept', [], ['Idempotency-Key' => 'wh-accept'])
        ->assertForbidden();

    $accepted = $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$opened['id'].'/accept', [], [
        'Idempotency-Key' => 'accept-unsold',
    ])->assertOk()->json('data');

    $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$opened['id'].'/accept', [], [
        'Idempotency-Key' => 'accept-unsold',
    ])->assertOk();

    expect($accepted['credit_note'])->toBeNull()
        ->and($accepted['refund_receivable'])->toBeNull()
        ->and($accepted['credited_amount'])->toBe(0)
        ->and(StockLot::query()->find($lot->id)->qty_damaged)->toBe(0)
        ->and(StockLot::query()->find($lot->id)->qty_on_hand)->toBe(3)
        ->and(CashEntry::query()->whereIn('type', [
            CashEntryType::SupplierPaymentOut,
            CashEntryType::SupplierRefundIn,
        ])->count())->toBe(0)
        ->and(StockMovement::query()->where('type', MovementType::SupplierReturn)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'supplier_return.accepted')->count())->toBe(1);
});

it('credits an open consignment payable when the damaged return is accepted', function () {
    $buyer = settlementUser('Procurement');
    $cashier = settlementUser('Cashier');
    $manager = settlementUser('Manager');
    $warehouse = settlementWarehouse('STORE');
    $supplier = settlementSupplier('UNILEVER', SupplyMode::Consignment);
    $soap = settlementProduct('SOAP', 100);
    $lot = settlementLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 3);

    $sale = settlementSale($cashier, $warehouse, $soap, null, [
        ['type' => 'cash', 'amount' => 100],
    ], 'sell-soap');
    $returnId = settlementReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 'damaged');
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->postJson('/api/v1/customer-returns/'.$returnId.'/refund', [], [
        'Idempotency-Key' => 'refund-damaged',
    ])->assertOk();

    $supplierReturn = SupplierReturn::query()->firstOrFail();
    expect($supplierReturn->sale_line_allocation_id)->not->toBeNull()
        ->and($supplierReturn->status)->toBe(SupplierReturnStatus::Draft);

    $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$supplierReturn->id.'/ship')->assertOk();
    $accepted = $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$supplierReturn->id.'/accept', [], [
        'Idempotency-Key' => 'accept-payable',
    ])->assertOk()->json('data');

    $bill = SupplierBill::query()->firstOrFail();

    expect($accepted['credited_amount'])->toBe(60)
        ->and($accepted['credit_note'])->toBeNull()
        ->and($accepted['refund_receivable'])->toBeNull()
        ->and($bill->credited_amount)->toBe(60)
        ->and($bill->status)->toBe(SupplierBillStatus::Credited)
        ->and(StockLot::query()->find($lot->id)->qty_damaged)->toBe(0)
        ->and(CashEntry::query()->whereIn('type', [
            CashEntryType::SupplierPaymentOut,
            CashEntryType::SupplierRefundIn,
        ])->count())->toBe(0);
});

it('shrinks an open outright bill by credit note and settles it at zero', function () {
    $buyer = settlementUser('Procurement');
    $warehouseUser = settlementUser('Warehouse');
    $received = receiveOutright($buyer, 'MILL', 'MUG', 70, 2);

    damageLot($warehouseUser, $received['warehouse_id'], $received['product_id'], 'damage-a');
    $first = openAndShip($warehouseUser, $received['lot_id'], 1, 'return-a');
    $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$first.'/accept', [], [
        'Idempotency-Key' => 'credit-partial',
    ])->assertOk();

    $bill = SupplierBill::query()->find($received['bill_id']);
    expect($bill->credited_amount)->toBe(70)
        ->and($bill->amount_paid)->toBe(0)
        ->and($bill->status)->toBe(SupplierBillStatus::Credited)
        ->and(CreditNote::query()->count())->toBe(1)
        ->and(CashEntry::query()->count())->toBe(0);

    damageLot($warehouseUser, $received['warehouse_id'], $received['product_id'], 'damage-b');
    $second = openAndShip($warehouseUser, $received['lot_id'], 1, 'return-b');
    $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$second.'/accept', [], [
        'Idempotency-Key' => 'credit-zero',
    ])->assertOk();

    $bill->refresh();
    expect($bill->credited_amount)->toBe(140)
        ->and($bill->amount_paid)->toBe(0)
        ->and($bill->status)->toBe(SupplierBillStatus::Settled)
        ->and(CreditNote::query()->sum('amount'))->toBe(140)
        ->and(SupplierRefundReceivable::query()->count())->toBe(0)
        ->and(CashEntry::query()->count())->toBe(0);
});

it('records a bank inflow after a paid lot damage claim is accepted', function () {
    $buyer = settlementUser('Procurement');
    $warehouseUser = settlementUser('Warehouse');
    $received = receiveOutright($buyer, 'MILL', 'MUG', 70, 1);

    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$received['bill_id']],
    ], ['Idempotency-Key' => 'pay-mug'])->assertCreated();

    damageLot($warehouseUser, $received['warehouse_id'], $received['product_id'], 'damage-paid');
    $returnId = openAndShip($warehouseUser, $received['lot_id'], 1, 'return-paid');

    $accepted = $this->actingAs($buyer)->postJson('/api/v1/supplier-returns/'.$returnId.'/accept', [], [
        'Idempotency-Key' => 'accept-paid',
    ])->assertOk()->json('data');

    expect($accepted['refund_receivable']['amount'])->toBe(70)
        ->and($accepted['refund_receivable']['status'])->toBe('open')
        ->and($accepted['credit_note'])->toBeNull()
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierRefundIn)->count())->toBe(0);

    $refunded = $this->actingAs($buyer)->postJson('/api/v1/supplier-refunds', [
        'supplier_return_id' => $returnId,
    ], ['Idempotency-Key' => 'refund-in'])->assertCreated()->json('data');

    $this->actingAs($buyer)->postJson('/api/v1/supplier-refunds', [
        'supplier_return_id' => $returnId,
    ], ['Idempotency-Key' => 'refund-in'])->assertCreated();

    expect($refunded['refund_receivable']['status'])->toBe('received')
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierRefundIn)->count())->toBe(1)
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierRefundIn)->value('amount'))->toBe(70)
        ->and(CashEntry::query()->where('type', CashEntryType::SupplierRefundIn)->value('location'))->toBe(CashLocation::Bank)
        ->and(AuditLog::query()->where('action', 'supplier_refund.posted')->count())->toBe(1);
});

it('leaves damaged quantity in place when the supplier rejects the return', function () {
    $warehouseUser = settlementUser('Warehouse');
    $buyer = settlementUser('Procurement');
    $warehouse = settlementWarehouse('STORE');
    $supplier = settlementSupplier('UNILEVER', SupplyMode::Consignment);
    $soap = settlementProduct('SOAP', 100);
    $lot = settlementLot($warehouse, $soap, $supplier, LotOwnership::Consignment, 60, 2);

    damageLot($warehouseUser, $warehouse->id, $soap->id, 'damage-reject');
    $returnId = openAndShip($warehouseUser, $lot->id, 1, 'return-reject');

    $this->actingAs($buyer)
        ->postJson('/api/v1/supplier-returns/'.$returnId.'/reject')
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected_by_supplier');

    expect(StockLot::query()->find($lot->id)->qty_damaged)->toBe(1)
        ->and(StockMovement::query()->where('type', MovementType::SupplierReturn)->count())->toBe(0)
        ->and(CashEntry::query()->count())->toBe(0);
});

it('reports till and bank as sums of cash entries', function () {
    $buyer = settlementUser('Procurement');
    $cashier = settlementUser('Cashier');
    $manager = settlementUser('Manager');
    $warehouse = settlementWarehouse('STORE');
    $product = settlementProduct('BOWL', 100);
    settlementLot($warehouse, $product, null, LotOwnership::Owned, 20, 5);

    $sale = settlementSale($cashier, $warehouse, $product, null, [
        ['type' => 'cash', 'amount' => 100],
    ], 'till-sale');
    $returnId = settlementReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 'resaleable');
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->postJson('/api/v1/customer-returns/'.$returnId.'/refund', [], [
        'Idempotency-Key' => 'till-refund',
    ])->assertOk();

    $received = receiveOutright($buyer, 'MILL', 'MUG', 70, 1);
    $this->actingAs($buyer)->postJson('/api/v1/supplier-settlements', [
        'bill_ids' => [$received['bill_id']],
    ], ['Idempotency-Key' => 'bank-out'])->assertCreated();

    $this->actingAs($manager)->getJson('/api/v1/cash-balances')
        ->assertOk()
        ->assertJsonPath('data.till', 0)
        ->assertJsonPath('data.bank', -70);

    $this->actingAs($buyer)->getJson('/api/v1/cash-entries?'.http_build_query([
        'filter' => ['location' => 'bank'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.0.type', 'supplier_payment_out')
        ->assertJsonPath('data.0.amount', 70);
});

function settlementUser(string $role, ?string $warehouseId = null): User
{
    $user = User::factory()->create(['warehouse_id' => $warehouseId]);
    $user->assignRole($role);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    return $user;
}

function settlementWarehouse(string $code): Warehouse
{
    return Warehouse::query()->create([
        'code' => $code,
        'name' => $code,
        'is_active' => true,
    ]);
}

function settlementProduct(string $sku, int $price): Product
{
    return Product::query()->create([
        'sku' => $sku,
        'name' => $sku,
        'sell_price' => $price,
        'track_stock' => true,
        'is_active' => true,
    ]);
}

function settlementSupplier(string $code, SupplyMode $mode): Supplier
{
    return Supplier::query()->create([
        'code' => $code,
        'name' => $code,
        'supply_mode' => $mode,
        'payment_terms_days' => 30,
        'status' => SupplierStatus::Active,
    ]);
}

function settlementLot(
    Warehouse $warehouse,
    Product $product,
    ?Supplier $supplier,
    LotOwnership $ownership,
    int $cost,
    int $quantity,
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
        'received_at' => now(),
    ]);

    $balance = StockBalance::query()->firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
        ['on_hand' => 0, 'damaged' => 0, 'in_transit' => 0],
    );
    $balance->on_hand += $quantity;
    $balance->save();

    return $lot;
}

function settlementCustomer(string $mobile, int $points): Customer
{
    return Customer::query()->create([
        'mobile' => $mobile,
        'points_balance' => $points,
    ]);
}

/**
 * @param  list<array{type: string, amount: int}>  $tenders
 * @return array<string, mixed>
 */
function settlementSale(
    User $cashier,
    Warehouse $warehouse,
    Product $product,
    ?Customer $customer,
    array $tenders,
    string $key,
): array {
    return test()->actingAs($cashier)->postJson('/api/v1/sales', [
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer?->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'tenders' => $tenders,
    ], ['Idempotency-Key' => $key])->assertCreated()->json('data');
}

function settlementReturn(User $cashier, string $saleId, string $lineId, string $reason): string
{
    return test()->actingAs($cashier)->postJson('/api/v1/customer-returns', [
        'sale_id' => $saleId,
        'lines' => [
            ['sale_line_id' => $lineId, 'quantity' => 1, 'reason' => $reason],
        ],
    ])->assertCreated()->json('data.id');
}

/**
 * @return array{bill_id: string, lot_id: string, warehouse_id: string, product_id: string}
 */
function receiveOutright(User $buyer, string $supplierCode, string $sku, int $cost, int $quantity): array
{
    $warehouse = settlementWarehouse($sku.'-WH');
    $product = settlementProduct($sku, 150);
    $supplier = settlementSupplier($supplierCode.'-'.$sku, SupplyMode::Outright);
    SupplierProduct::query()->create([
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'agreed_unit_cost' => $cost,
    ]);

    $order = test()->actingAs($buyer)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'lines' => [
            ['product_id' => $product->id, 'quantity' => $quantity],
        ],
    ])->assertCreated()->json('data');

    test()->actingAs($buyer)
        ->postJson('/api/v1/purchase-orders/'.$order['id'].'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $receipt = test()->actingAs($buyer)->postJson('/api/v1/goods-receipts', [
        'purchase_order_id' => $order['id'],
        'lines' => [
            ['product_id' => $product->id, 'quantity' => $quantity],
        ],
    ], ['Idempotency-Key' => 'receipt-'.$supplierCode.'-'.$sku])->assertCreated()->json('data');

    return [
        'bill_id' => $receipt['bill']['id'],
        'lot_id' => $receipt['lots'][0]['id'],
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ];
}

function damageLot(User $actor, string $warehouseId, string $productId, string $key): void
{
    test()->actingAs($actor)->postJson('/api/v1/adjustments', [
        'warehouse_id' => $warehouseId,
        'product_id' => $productId,
        'quantity' => 1,
        'reason' => 'damage',
    ], ['Idempotency-Key' => $key])->assertCreated();
}

function openAndShip(User $actor, string $lotId, int $quantity, string $suffix): string
{
    $id = test()->actingAs($actor)->postJson('/api/v1/supplier-returns', [
        'stock_lot_id' => $lotId,
        'quantity' => $quantity,
    ])->assertCreated()->json('data.id');

    test()->actingAs($actor)->postJson('/api/v1/supplier-returns/'.$id.'/ship')->assertOk();

    return $id;
}
