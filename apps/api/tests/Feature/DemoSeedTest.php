<?php

use App\Actions\CreatePurchaseOrdersFromAlerts;
use App\Actions\ListReorderAlerts;
use App\Enums\LotOwnership;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierBillStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockLot;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;

it('seeds the hart and field scenes without a completed sale', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoSeeder::class);

    $rahim = Customer::query()->where('mobile', '+8801711000001')->firstOrFail();
    $nameless = Customer::query()->where('mobile', '+8801711000002')->firstOrFail();
    $hs = Warehouse::query()->where('code', 'HS')->firstOrFail();
    $nadia = User::query()->where('email', 'nadia@hartandfield.test')->firstOrFail();
    $lina = User::query()->where('email', 'lina@hartandfield.test')->firstOrFail();
    $karim = User::query()->where('email', 'karim@hartandfield.test')->firstOrFail();
    $soap = Product::query()->where('barcode', '8941000000017')->firstOrFail();
    $mug = Product::query()->where('barcode', '8941000000024')->firstOrFail();

    expect(Customer::query()->count())->toBe(2)
        ->and(Product::query()->count())->toBe(40)
        ->and(Sale::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'admin@meridian.test')->exists())->toBeFalse()
        ->and($rahim->name)->toBe('Rahim Uddin')
        ->and($rahim->points_balance)->toBe(30)
        ->and($nameless->name)->toBeNull()
        ->and($nameless->points_balance)->toBe(0)
        ->and($lina->warehouse_id)->toBe($hs->id)
        ->and($karim->warehouse?->code)->toBe('DC');

    $soapLot = StockLot::query()
        ->where('product_id', $soap->id)
        ->where('warehouse_id', $hs->id)
        ->firstOrFail();

    expect($soapLot->ownership)->toBe(LotOwnership::Consignment)
        ->and($soapLot->unit_cost)->toBe(60)
        ->and($soapLot->qty_on_hand)->toBeGreaterThan(0)
        ->and(SupplierBill::query()->where('source_id', $soapLot->goods_receipt_id)->exists())->toBeFalse();

    $mugLot = StockLot::query()
        ->where('product_id', $mug->id)
        ->where('warehouse_id', $hs->id)
        ->where('ownership', LotOwnership::Owned)
        ->firstOrFail();
    $bill = SupplierBill::query()->where('source_id', $mugLot->goods_receipt_id)->firstOrFail();

    expect($mugLot->unit_cost)->toBe(70)
        ->and($mugLot->qty_on_hand)->toBeGreaterThan(0)
        ->and($bill->status)->toBe(SupplierBillStatus::Open)
        ->and($bill->amount_paid)->toBe(0)
        ->and($bill->amount)->toBe($mugLot->qty_received * 70);

    $pending = PurchaseOrder::query()
        ->where('requester_id', $nadia->id)
        ->where('status', PurchaseOrderStatus::PendingApproval)
        ->firstOrFail();

    expect($pending->total)->toBeGreaterThanOrEqual(20000)
        ->and(AuditLog::query()
            ->where('action', 'purchase_order.submitted')
            ->where('auditable_id', $pending->id)
            ->where('actor_id', $nadia->id)
            ->exists())->toBeTrue();

    $alerts = app(ListReorderAlerts::class)();

    expect($alerts)->not->toBeEmpty();

    foreach ($alerts as $alert) {
        expect($alert['warehouse']['code'])->toBe('DC')
            ->and($alert['preferred_supplier']['code'])->toBe('HART-MILL');
    }

    $orders = app(CreatePurchaseOrdersFromAlerts::class)($nadia, [
        'lines' => array_map(
            fn (array $alert): array => [
                'warehouse_id' => $alert['warehouse_id'],
                'product_id' => $alert['product_id'],
            ],
            $alerts,
        ),
    ]);

    expect($orders)->toHaveCount(1)
        ->and($orders[0]->total)->toBeGreaterThanOrEqual(20000)
        ->and($orders[0]->status)->toBe(PurchaseOrderStatus::Draft);
});
