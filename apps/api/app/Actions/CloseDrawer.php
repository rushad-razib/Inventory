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

final class CloseDrawer
{
    public function __construct(
        private readonly CashBalances $balances,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(User $actor, DrawerSession $session, int $counted, ?Request $request = null): DrawerSession
    {
        if ($counted < 0) {
            throw new LedgerException('invalid-state', 'The counted amount cannot be negative.', 422);
        }

        if ($session->cashier_id !== $actor->id && ! $actor->can('cash.manage')) {
            throw new LedgerException('forbidden', 'You can only close your own drawer.', 403);
        }

        return DB::transaction(function () use ($actor, $session, $counted, $request): DrawerSession {
            CompanySetting::query()->lockForUpdate()->first();
            $locked = DrawerSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DrawerStatus::Open) {
                throw new LedgerException('invalid-state', 'This drawer is already closed.');
            }

            $expected = $locked->balance();
            $variance = $counted - $expected;
            $cashBefore = $this->balances->balance(CashLocation::Cash) + $this->balances->balance(CashLocation::Till);

            if ($variance > 0) {
                $this->entry($locked, $actor, CashLocation::Drawer, CashDirection::In, CashEntryType::DrawerOverage, $variance);
            }

            if ($variance < 0) {
                $this->entry($locked, $actor, CashLocation::Drawer, CashDirection::Out, CashEntryType::DrawerShortage, -$variance);
            }

            if ($counted > 0) {
                $this->entry($locked, $actor, CashLocation::Drawer, CashDirection::Out, CashEntryType::DrawerDeposit, $counted);
                $this->entry($locked, $actor, CashLocation::Cash, CashDirection::In, CashEntryType::DrawerDeposit, $counted);
            }

            $locked->fill([
                'status' => DrawerStatus::Closed,
                'counted_amount' => $counted,
                'expected_amount' => $expected,
                'variance' => $variance,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'open_key' => null,
            ]);
            $locked->save();

            $this->audit->write('drawer.closed', $locked, $actor, [
                'expected' => $expected,
                'cash' => $cashBefore,
                'drawer' => $expected,
            ], [
                'counted' => $counted,
                'expected' => $expected,
                'variance' => $variance,
                'cash' => $cashBefore + $counted,
                'drawer' => 0,
            ], $request);

            return $locked->refresh();
        });
    }

    private function entry(
        DrawerSession $session,
        User $actor,
        CashLocation $location,
        CashDirection $direction,
        CashEntryType $type,
        int $amount,
    ): void {
        CashEntry::query()->create([
            'location' => $location,
            'direction' => $direction,
            'type' => $type,
            'amount' => $amount,
            'source_type' => $session->getMorphClass(),
            'source_id' => $session->id,
            'drawer_session_id' => $session->id,
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);
    }
}
