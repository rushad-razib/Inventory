<?php

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\LotOwnership;
use App\Models\AuditLog;
use App\Models\CashEntry;
use App\Models\CompanySetting;
use App\Models\DrawerSession;
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

it('moves a float out of main cash and blocks a sale until the drawer is open', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $admin = posUser('Admin');
    $cashier = posUser('Cashier');

    $this->actingAs($admin)->postJson('/api/v1/cash-movements', [
        'location' => 'cash',
        'direction' => 'in',
        'amount' => 5000,
        'note' => 'Opening safe',
    ])->assertCreated();

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'no-drawer'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 150],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('type', '/problems/invalid-state');

    $this->actingAs($cashier)->postJson('/api/v1/drawers', [
        'warehouse_id' => $warehouse->id,
        'opening_balance' => 1000,
    ])->assertCreated()
        ->assertJsonPath('data.opening_amount', 1000)
        ->assertJsonPath('data.balance', 1000);

    expect(AuditLog::query()->where('action', 'drawer.opened')->count())->toBe(1);

    $this->actingAs($admin)->getJson('/api/v1/cash-balances')
        ->assertOk()
        ->assertJsonPath('data.cash', 4000)
        ->assertJsonPath('data.drawer', 1000);
});

it('deposits the counted drawer and records a shortage', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 500);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $admin = posUser('Admin');
    $cashier = posUser('Cashier');

    $this->actingAs($admin)->postJson('/api/v1/cash-movements', [
        'location' => 'cash',
        'direction' => 'in',
        'amount' => 5000,
        'note' => 'Float fund',
    ])->assertCreated();

    $drawerId = $this->actingAs($cashier)->postJson('/api/v1/drawers', [
        'warehouse_id' => $warehouse->id,
        'opening_balance' => 1000,
    ])->assertCreated()->json('data.id');

    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'drawer-sale'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 500],
        ]))
        ->assertCreated();

    $this->actingAs($cashier)->postJson('/api/v1/drawers/'.$drawerId.'/close', [
        'counted_amount' => 1480,
    ])->assertOk()
        ->assertJsonPath('data.expected_amount', 1500)
        ->assertJsonPath('data.variance', -20)
        ->assertJsonPath('data.balance', 0)
        ->assertJsonPath('data.status', 'closed');

    $closed = AuditLog::query()->where('action', 'drawer.closed')->first();
    expect($closed)->not->toBeNull()
        ->and($closed->after['counted'])->toBe(1480)
        ->and($closed->after['variance'])->toBe(-20);

    $this->actingAs($admin)->getJson('/api/v1/cash-balances')
        ->assertOk()
        ->assertJsonPath('data.cash', 5480)
        ->assertJsonPath('data.drawer', 0);

    expect(CashEntry::query()->where('type', CashEntryType::DrawerShortage)->value('amount'))->toBe(20);
});

it('pays a cash refund from the open drawer and voids a closed sale from main cash', function () {
    $warehouse = posWarehouse('STORE');
    $product = posProduct('MUG', 150);
    posLot($warehouse, $product, null, LotOwnership::Owned, 70, 5, now());
    $admin = posUser('Admin');
    $cashier = posUser('Cashier');
    $manager = posUser('Manager');

    $this->actingAs($admin)->postJson('/api/v1/cash-movements', [
        'location' => 'cash',
        'direction' => 'in',
        'amount' => 5000,
        'note' => 'Float fund',
    ])->assertCreated();

    $drawerId = $this->actingAs($cashier)->postJson('/api/v1/drawers', [
        'warehouse_id' => $warehouse->id,
        'opening_balance' => 1000,
    ])->json('data.id');

    $sale = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'refund-sale'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 150],
        ]))
        ->assertCreated()
        ->json('data');

    $returnId = posReturn($cashier, $sale['id'], $sale['lines'][0]['id'], 1);
    $this->actingAs($manager)->postJson('/api/v1/customer-returns/'.$returnId.'/approve')->assertOk();
    $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'refund-open'])
        ->postJson('/api/v1/customer-returns/'.$returnId.'/refund')
        ->assertOk();

    expect(CashEntry::query()->where('type', CashEntryType::CustomerRefundOut)->value('location'))
        ->toBe(CashLocation::Drawer);

    $second = $this->actingAs($cashier)->withHeaders(['Idempotency-Key' => 'void-later'])
        ->postJson('/api/v1/sales', posSaleBody($warehouse, $product, null, 1, [
            ['type' => 'cash', 'amount' => 150],
        ]))
        ->assertCreated()
        ->json('data');

    $this->actingAs($cashier)->postJson('/api/v1/drawers/'.$drawerId.'/close', [
        'counted_amount' => 1000,
    ])->assertOk();

    $this->actingAs($manager)->postJson('/api/v1/sales/'.$second['id'].'/void')->assertOk();

    $reversal = CashEntry::query()
        ->where('source_id', $second['id'])
        ->where('direction', CashDirection::Out)
        ->first();

    expect($reversal)->not->toBeNull()
        ->and($reversal->location)->toBe(CashLocation::Cash)
        ->and($reversal->drawer_session_id)->toBeNull();
});

it('audits a withdrawal and refuses one larger than the balance', function () {
    $admin = posUser('Admin');
    $cashier = posUser('Cashier');

    $this->actingAs($cashier)->postJson('/api/v1/cash-movements', [
        'location' => 'cash',
        'direction' => 'in',
        'amount' => 100,
        'note' => 'Not allowed',
    ])->assertForbidden();

    $this->actingAs($admin)->postJson('/api/v1/cash-movements', [
        'location' => 'cash',
        'direction' => 'in',
        'amount' => 800,
        'note' => 'Safe top-up',
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/cash-movements', [
        'location' => 'bank',
        'direction' => 'out',
        'amount' => 50,
        'note' => 'Bank charge',
    ])->assertStatus(422);

    $this->actingAs($admin)->postJson('/api/v1/cash-movements', [
        'location' => 'cash',
        'direction' => 'out',
        'amount' => 300,
        'note' => 'Owner draw',
    ])->assertCreated();

    $audit = AuditLog::query()->where('action', 'cash.adjusted')->get()
        ->first(fn (AuditLog $row): bool => ($row->after['direction'] ?? null) === 'out');

    expect($audit)->not->toBeNull()
        ->and($audit->after['amount'])->toBe(300)
        ->and($audit->after['note'])->toBe('Owner draw')
        ->and($audit->after['balance'])->toBe(500)
        ->and($audit->created_at)->not->toBeNull();

    expect(DrawerSession::query()->count())->toBe(0);
});
