<?php

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\LotOwnership;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RefundReceivableStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierReturnStatus;
use App\Enums\SupplyMode;
use App\Models\Adjustment;
use App\Models\AuditLog;
use App\Models\CashEntry;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierRefundReceivable;
use App\Models\SupplierReturn;
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

it('rejects insight routes without authentication or permission', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    $this->getJson('/api/v1/reports/demand')->assertUnauthorized();
    $this->getJson('/api/v1/reports/roi/export')->assertUnauthorized();
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized();

    $cashier = posUser('Cashier');
    $this->actingAs($cashier)->getJson('/api/v1/dashboard')->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/reports/stock')->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/reports/movements')->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/audit-logs')->assertForbidden();

    $procurement = posUser('Procurement');
    $this->actingAs($procurement)->getJson('/api/v1/reports/sales')->assertOk();
    $this->actingAs($procurement)->getJson('/api/v1/audit-logs')->assertForbidden();
});

it('keeps the sold mug in demand and roi and drops a refunded or voided line', function () {
    $warehouse = posWarehouse('STORE');
    $tableware = Category::query()->create(['name' => 'Tableware']);
    $soap = posProduct('SOAP', 100);
    $mug = posProduct('MUG', 150);
    $mug->category_id = $tableware->id;
    $mug->save();
    $free = posProduct('FREE', 40);
    $bowl = posProduct('BOWL', 80);
    $soap->category_id = $tableware->id;
    $soap->save();
    posLot($warehouse, $soap, null, LotOwnership::Owned, 60, 5, now()->subDay());
    posLot($warehouse, $mug, null, LotOwnership::Owned, 70, 5, now());
    posLot($warehouse, $free, null, LotOwnership::Owned, 0, 5, now());
    posLot($warehouse, $bowl, null, LotOwnership::Owned, 20, 5, now());
    $customer = posCustomer('+8801711000091', 30);
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-mug'])
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
        ])->assertCreated()->json('data');

    $lines = collect($sale['lines'])->keyBy(fn (array $line) => $line['product']['sku']);
    $returnId = posReturn($cashier, $sale['id'], $lines['MUG']['id'], 1);

    $pending = $this->actingAs($manager)->getJson('/api/v1/reports/demand?group=product')->assertOk()->json('data');
    expect(collect($pending)->pluck('group_label')->all())->toContain('MUG · MUG');

    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-refund'])
        ->postJson('/api/v1/customer-returns/'.$returnId.'/refund')
        ->assertOk();

    $demand = collect($this->actingAs($manager)->getJson('/api/v1/reports/demand?group=product')->assertOk()->json('data'));
    expect($demand->pluck('group_label')->all())->not->toContain('MUG · MUG')
        ->and($demand->firstWhere('group_label', 'SOAP · SOAP'))->toMatchArray([
            'net_units' => 1,
            'net_taka' => 88,
        ]);

    $roi = collect($this->actingAs($manager)->getJson('/api/v1/reports/roi?group=product')->assertOk()->json('data'));
    expect($roi->firstWhere('group_label', 'SOAP · SOAP'))->toMatchArray([
        'net_units' => 1,
        'net_sales' => 88,
        'cost' => 60,
    ])->and($roi->pluck('group_label')->all())->not->toContain('MUG · MUG');

    $kept = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-kept-mug'])
        ->postJson('/api/v1/sales', [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'lines' => [
                ['product_id' => $mug->id, 'quantity' => 1],
            ],
            'tenders' => [
                ['type' => 'points', 'amount' => 18],
                ['type' => 'cash', 'amount' => 132],
            ],
        ])->assertCreated()->json('data');

    expect($kept['lines'][0]['points_redeemed'])->toBe(18);

    $mugRoi = collect($this->actingAs($manager)->getJson('/api/v1/reports/roi?group=product')->json('data'))
        ->firstWhere('group_label', 'MUG · MUG');
    expect($mugRoi)->toMatchArray([
        'net_units' => 1,
        'net_sales' => 132,
        'cost' => 70,
        'roi' => '0.8857',
    ]);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-free'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $free, null, 1, [
            ['type' => 'cash', 'amount' => 40],
        ]))->assertCreated();

    $withFree = collect($this->actingAs($manager)->getJson('/api/v1/reports/roi?group=product')->json('data'));
    expect($withFree->pluck('group_label')->all())->not->toContain('FREE · FREE')
        ->and(collect($this->actingAs($manager)->getJson('/api/v1/reports/demand?group=product')->json('data'))->pluck('group_label'))
        ->toContain('FREE · FREE');

    $category = collect($this->actingAs($manager)->getJson('/api/v1/reports/roi?group=category')->json('data'))
        ->firstWhere('group_label', 'Tableware');
    expect($category['net_sales'])->toBe(88 + 132)
        ->and($category['cost'])->toBe(60 + 70)
        ->and($category['roi'])->toBe('0.6923');

    $voided = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-void'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $bowl, null, 1, [
            ['type' => 'cash', 'amount' => 80],
        ]))->assertCreated()->json('data');

    $this->actingAs($manager)->postJson('/api/v1/sales/'.$voided['id'].'/void')->assertOk();

    expect(collect($this->actingAs($manager)->getJson('/api/v1/reports/demand?group=product')->json('data'))->pluck('group_label')->all())
        ->not->toContain('BOWL · BOWL');

    $csv = insightCsv('/api/v1/reports/roi/export?group=product');
    expect($csv[0])->toBe(['group_label', 'net_units', 'net_sales', 'cost', 'roi'])
        ->and(collect($csv)->first(fn (array $row) => ($row[0] ?? '') === 'MUG · MUG'))->toBe(['MUG · MUG', '1', '132', '70', '0.8857']);
});

it('reports rung-up tenders by day without rewriting them after a refund', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $customer = posCustomer('+8801711000092', 30);
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-sales'])
        ->postJson('/api/v1/sales', [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'tenders' => [
                ['type' => 'points', 'amount' => 18],
                ['type' => 'cash', 'amount' => 132],
            ],
        ])->assertCreated()->json('data');

    $returnId = posReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-sales-refund'])
        ->postJson('/api/v1/customer-returns/'.$returnId.'/refund')->assertOk();

    $row = $this->actingAs($manager)->getJson('/api/v1/reports/sales')->assertOk()->json('data.0');
    expect($row['sale_count'])->toBe(1)
        ->and($row['points_redeemed'])->toBe(18)
        ->and($row['cash'])->toBe(132)
        ->and($row['card'])->toBe(0);

    $csv = insightCsv('/api/v1/reports/sales/export');
    expect($csv[1][2])->toBe('18')
        ->and($csv[1][3])->toBe('132');
});

it('separates owned and consignment stock and exports the same rows', function () {
    $warehouse = posWarehouse('STORE');
    $owned = posProduct('MUG', 150);
    $consigned = posProduct('SOAP', 100);
    posLot($warehouse, $owned, null, LotOwnership::Owned, 70, 4, now());
    posLot($warehouse, $consigned, posSupplier('UNI', SupplyMode::Consignment), LotOwnership::Consignment, 40, 3, now());

    $rows = collect($this->actingAs(posUser('Manager'))->getJson('/api/v1/reports/stock')->assertOk()->json('data'));
    expect($rows->firstWhere('sku', 'MUG'))->toMatchArray([
        'owned_on_hand' => 4,
        'owned_value' => 280,
        'consignment_on_hand' => 0,
    ])->and($rows->firstWhere('sku', 'SOAP'))->toMatchArray([
        'consignment_on_hand' => 3,
        'consignment_value' => 120,
        'owned_on_hand' => 0,
    ]);

    $csv = insightCsv('/api/v1/reports/stock/export');
    expect(collect($csv)->first(fn (array $row) => ($row[2] ?? '') === 'MUG')[4])->toBe('4')
        ->and(collect($csv)->first(fn (array $row) => ($row[2] ?? '') === 'MUG')[6])->toBe('280');
});

it('filters movement history and exports every matching row', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 100);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-move'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 100],
        ]))->assertCreated();

    $rows = $this->actingAs($manager)->getJson('/api/v1/reports/movements?filter[type]=sale&filter[product_id]='.$product->id)
        ->assertOk()
        ->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe('sale')
        ->and($rows[0]['quantity'])->toBe(-1);

    $csv = insightCsv('/api/v1/reports/movements/export?filter[type]=sale&filter[product_id]='.$product->id);
    expect($csv[0][3])->toBe('type')
        ->and($csv[1][3])->toBe('sale')
        ->and($csv[1][4])->toBe('-1');
});

it('sums supplier bills, payments, and received refunds', function () {
    $supplier = posSupplier('ACME', SupplyMode::Outright);
    $other = posSupplier('OTHER', SupplyMode::Outright);
    $manager = posUser('Manager');

    SupplierBill::query()->create([
        'supplier_id' => $supplier->id,
        'source_type' => Supplier::class,
        'source_id' => $supplier->id,
        'status' => SupplierBillStatus::Open,
        'amount' => 280,
        'amount_paid' => 100,
        'credited_amount' => 0,
        'due_on' => now()->toDateString(),
    ]);
    SupplierBill::query()->create([
        'supplier_id' => $other->id,
        'source_type' => Supplier::class,
        'source_id' => $other->id,
        'status' => SupplierBillStatus::Open,
        'amount' => 50,
        'amount_paid' => 0,
        'credited_amount' => 0,
        'due_on' => now()->addDay()->toDateString(),
    ]);
    SupplierRefundReceivable::query()->create([
        'supplier_id' => $supplier->id,
        'supplier_return_id' => insightReturnId($supplier),
        'amount' => 70,
        'status' => RefundReceivableStatus::Received,
    ]);

    $row = collect($this->actingAs($manager)->getJson('/api/v1/reports/supplier-spend')->assertOk()->json('data'))
        ->firstWhere('supplier_name', 'ACME');
    expect($row)->toMatchArray(['billed' => 280, 'paid' => 100, 'refunded' => 70]);

    $csv = insightCsv('/api/v1/reports/supplier-spend/export');
    expect(collect($csv)->first(fn (array $line) => ($line[0] ?? '') === 'ACME'))->toBe(['ACME', '280', '100', '70']);
});

it('compares ordered and received quantities and skips cancelled orders', function () {
    $warehouse = posWarehouse('STORE');
    $supplier = posSupplier('ACME', SupplyMode::Outright);
    $product = posProduct('MUG', 150);
    $requester = posUser('Procurement');
    $manager = posUser('Manager');

    $open = PurchaseOrder::query()->create([
        'number' => 'PO-INSIGHT-1',
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::PartiallyReceived,
        'requester_id' => $requester->id,
        'subtotal' => 700,
        'tax' => 0,
        'total' => 700,
    ]);
    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $open->id,
        'product_id' => $product->id,
        'quantity_ordered' => 10,
        'quantity_received' => 4,
        'unit_cost' => 70,
    ]);
    PurchaseOrder::query()->create([
        'number' => 'PO-INSIGHT-2',
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Cancelled,
        'requester_id' => $requester->id,
        'subtotal' => 70,
        'tax' => 0,
        'total' => 70,
    ]);

    $rows = $this->actingAs($manager)->getJson('/api/v1/reports/purchase-orders')->assertOk()->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['quantity_ordered'])->toBe(10)
        ->and($rows[0]['quantity_received'])->toBe(4)
        ->and($rows[0]['status'])->toBe('partially_received');

    $csv = insightCsv('/api/v1/reports/purchase-orders/export');
    expect($csv[1][0])->toBe('PO-INSIGHT-1')
        ->and($csv[1][3])->toBe('10')
        ->and($csv[1][4])->toBe('4');
});

it('fills dashboard tiles from the ledgers and includes the chart series', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 100);
    $product->reorder_point = 10;
    $product->save();
    $soap = posProduct('SOAP', 40);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 4, now());
    posLot($warehouse, $soap, posSupplier('UNI', SupplyMode::Consignment), LotOwnership::Consignment, 40, 2, now());
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');
    $supplier = posSupplier('ACME', SupplyMode::Outright);

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-today'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 100],
        ]))->assertCreated();

    PurchaseOrder::query()->create([
        'number' => 'PO-DASH-1',
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
        'requester_id' => $manager->id,
        'subtotal' => 70,
        'tax' => 0,
        'total' => 70,
    ]);
    PurchaseOrder::query()->create([
        'number' => 'PO-DASH-2',
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::PendingApproval,
        'requester_id' => $manager->id,
        'subtotal' => 70,
        'tax' => 0,
        'total' => 70,
    ]);
    PurchaseOrder::query()->create([
        'number' => 'PO-DASH-3',
        'supplier_id' => $supplier->id,
        'destination_warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Cancelled,
        'requester_id' => $manager->id,
        'subtotal' => 70,
        'tax' => 0,
        'total' => 70,
    ]);
    Adjustment::query()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'reason' => AdjustmentReason::Count,
        'status' => AdjustmentStatus::PendingApproval,
        'requester_id' => $cashier->id,
    ]);
    SupplierBill::query()->create([
        'supplier_id' => $supplier->id,
        'source_type' => Supplier::class,
        'source_id' => $supplier->id,
        'status' => SupplierBillStatus::Open,
        'amount' => 280,
        'amount_paid' => 100,
        'credited_amount' => 0,
        'due_on' => now()->toDateString(),
    ]);
    SupplierBill::query()->create([
        'supplier_id' => $supplier->id,
        'source_type' => Supplier::class,
        'source_id' => $supplier->id,
        'status' => SupplierBillStatus::Open,
        'amount' => 50,
        'amount_paid' => 0,
        'credited_amount' => 0,
        'due_on' => now()->addDay()->toDateString(),
    ]);
    CashEntry::query()->create([
        'location' => CashLocation::Bank,
        'direction' => CashDirection::In,
        'type' => CashEntryType::SaleCardIn,
        'amount' => 40,
        'source_type' => Sale::class,
        'source_id' => Sale::query()->first()->id,
        'actor_id' => $cashier->id,
        'occurred_at' => now(),
    ]);

    $dashboard = $this->actingAs($manager)->getJson('/api/v1/dashboard')->assertOk()->json();

    expect($dashboard['data']['tiles'])->toMatchArray([
        'owned_value' => 210,
        'consignment_value' => 80,
        'low_stock' => 1,
        'sales_today' => 100,
        'open_purchase_orders' => 2,
        'pending_approvals' => 2,
        'payables_due' => 180,
        'till' => 100,
        'bank' => 40,
    ])->and($dashboard['data']['demand'])->not->toBeEmpty()
        ->and($dashboard['meta']['group'])->toBe('product');
});

it('filters the audit log by actor, action, and auditable type', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 100);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'insight-audit'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 100],
        ]))->assertCreated();

    expect(AuditLog::query()->where('action', 'sale.posted')->count())->toBe(1);

    $rows = $this->actingAs($manager)->getJson('/api/v1/audit-logs?filter[action]=sale.posted&filter[auditable_type]=Sale&filter[actor_id]='.$cashier->id)
        ->assertOk()
        ->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['action'])->toBe('sale.posted')
        ->and($rows[0]['auditable_type'])->toBe('Sale')
        ->and($rows[0]['actor']['id'])->toBe($cashier->id);
});

/**
 * @return list<list<string|null>>
 */
function insightCsv(string $path): array
{
    $response = test()->get($path)->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $lines = array_map(str_getcsv(...), preg_split("/\r\n|\n|\r/", trim($response->streamedContent())) ?: []);

    return array_values(array_filter($lines, fn (array $line): bool => $line !== [null] && $line !== ['']));
}

function insightReturnId(Supplier $supplier): string
{
    $warehouse = posWarehouse('RET-'.$supplier->code);
    $product = posProduct('RET-'.$supplier->code, 70);
    $lot = posLot($warehouse, $product, $supplier, LotOwnership::Owned, 70, 1, now());

    return SupplierReturn::query()->create([
        'stock_lot_id' => $lot->id,
        'quantity' => 1,
        'unit_cost' => 70,
        'status' => SupplierReturnStatus::Accepted,
    ])->id;
}
