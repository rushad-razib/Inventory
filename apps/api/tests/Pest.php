<?php

use App\Actions\OpenDrawer;
use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Models\CashEntry;
use App\Models\DrawerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function openDrawerFor(User $cashier, string $warehouseId, int $float = 100000): void
{
    if (DrawerSession::openFor($cashier) !== null) {
        return;
    }

    CashEntry::query()->create([
        'location' => CashLocation::Cash,
        'direction' => CashDirection::In,
        'type' => CashEntryType::CashAdjustment,
        'amount' => $float,
        'source_type' => $cashier->getMorphClass(),
        'source_id' => $cashier->id,
        'actor_id' => $cashier->id,
        'occurred_at' => now(),
    ]);

    app(OpenDrawer::class)($cashier, $warehouseId, $float);
}
