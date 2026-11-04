<?php

namespace App\Actions;

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\DrawerStatus;
use App\Exceptions\LedgerException;
use App\Models\CashEntry;
use App\Models\CompanySetting;
use App\Models\DrawerSession;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CashBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OpenDrawer
{
    public function __construct(
        private readonly CashBalances $balances,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(User $actor, string $warehouseId, int $opening, ?Request $request = null): DrawerSession
    {
        if ($opening < 1) {
            throw new LedgerException('invalid-state', 'The opening balance must be at least 1.', 422);
        }

        if ($actor->warehouse_id !== null && $actor->warehouse_id !== $warehouseId) {
            throw new LedgerException('forbidden', 'You can only open a drawer at your pinned warehouse.', 403);
        }

        return DB::transaction(function () use ($actor, $warehouseId, $opening, $request): DrawerSession {
            CompanySetting::query()->lockForUpdate()->first();

            $open = DrawerSession::query()
                ->where('cashier_id', $actor->id)
                ->where('status', DrawerStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw new LedgerException('invalid-state', 'You already have an open drawer.');
            }

            $before = $this->balances->balance(CashLocation::Cash) + $this->balances->balance(CashLocation::Till);
            if ($before < $opening) {
                throw new LedgerException('invalid-state', 'Main cash does not cover that opening balance.', 422);
            }

            $session = DrawerSession::query()->create([
                'cashier_id' => $actor->id,
                'warehouse_id' => $warehouseId,
                'status' => DrawerStatus::Open,
                'opening_amount' => $opening,
                'opened_at' => now(),
                'open_key' => $actor->id,
            ]);

            $this->entry($session, $actor, CashLocation::Cash, CashDirection::Out, $opening);
            $this->entry($session, $actor, CashLocation::Drawer, CashDirection::In, $opening);

            $this->audit->write('drawer.opened', $session, $actor, [
                'cash' => $before,
            ], [
                'cash' => $before - $opening,
                'opening_balance' => $opening,
                'warehouse_id' => $warehouseId,
            ], $request);

            return $session->refresh();
        });
    }

    private function entry(
        DrawerSession $session,
        User $actor,
        CashLocation $location,
        CashDirection $direction,
        int $amount,
    ): void {
        CashEntry::query()->create([
            'location' => $location,
            'direction' => $direction,
            'type' => CashEntryType::DrawerFloat,
            'amount' => $amount,
            'source_type' => $session->getMorphClass(),
            'source_id' => $session->id,
            'drawer_session_id' => $session->id,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);
    }
}
