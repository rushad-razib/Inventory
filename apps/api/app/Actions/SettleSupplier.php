<?php

namespace App\Actions;

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\RefundReceivableStatus;
use App\Enums\SupplierBillStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\SupplierReturnResource;
use App\Http\Resources\SupplierSettlementResource;
use App\Models\CashEntry;
use App\Models\SupplierBill;
use App\Models\SupplierCredit;
use App\Models\SupplierRefundReceivable;
use App\Models\SupplierReturn;
use App\Models\SupplierSettlement;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use Illuminate\Http\Request;

final class SettleSupplier
{
    public function __construct(
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{bill_ids: list<string>}  $input
     * @return array{status: int, data: array<string, mixed>}
     */
    public function pay(User $actor, array $input, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'supplier_settlement.create',
            $key,
            $hash,
            function () use ($actor, $input, $request) {
                $ids = array_values(array_unique($input['bill_ids']));
                $bills = SupplierBill::query()
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($bills->count() !== count($ids)) {
                    throw new LedgerException('invalid-state', 'One or more bills do not exist.', 422);
                }

                if ($bills->isEmpty()) {
                    throw new LedgerException('invalid-state', 'Choose at least one bill.', 422);
                }

                $supplierId = $bills->firstOrFail()->supplier_id;

                if ($bills->contains(fn (SupplierBill $bill): bool => $bill->supplier_id !== $supplierId)) {
                    throw new LedgerException('invalid-state', 'A settlement pays bills from one supplier.');
                }

                $openTotal = 0;

                foreach ($bills as $bill) {
                    if ($bill->status === SupplierBillStatus::Settled || $bill->remainder() <= 0) {
                        throw new LedgerException('invalid-state', 'Only bills with an open remainder can be paid.');
                    }

                    $openTotal += $bill->remainder();
                }

                $credits = SupplierCredit::query()
                    ->where('supplier_id', $supplierId)
                    ->whereColumn('amount_applied', '<', 'amount')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $unapplied = (int) $credits->sum(fn (SupplierCredit $credit): int => $credit->unapplied());
                $applied = min($openTotal, $unapplied);
                $cash = $openTotal - $applied;

                $settlement = SupplierSettlement::query()->create([
                    'supplier_id' => $supplierId,
                    'amount' => $cash,
                    'actor_id' => $actor->id,
                ]);

                if ($cash > 0) {
                    $entry = CashEntry::query()->create([
                        'location' => CashLocation::Bank,
                        'direction' => CashDirection::Out,
                        'type' => CashEntryType::SupplierPaymentOut,
                        'amount' => $cash,
                        'source_type' => $settlement->getMorphClass(),
                        'source_id' => $settlement->id,
                        'actor_id' => $actor->id,
                        'occurred_at' => now(),
                    ]);
                    $settlement->cash_entry_id = $entry->id;
                    $settlement->save();
                }

                foreach ($bills as $bill) {
                    $remainder = $bill->remainder();
                    $settlement->bills()->attach($bill->id, ['amount' => $remainder]);
                    $bill->amount_paid += $remainder;
                    $bill->status = SupplierBillStatus::Settled;
                    $bill->save();
                }

                $remainingCredit = $applied;

                foreach ($credits as $credit) {
                    if ($remainingCredit === 0) {
                        break;
                    }

                    $take = min($remainingCredit, $credit->unapplied());
                    $credit->amount_applied += $take;
                    $credit->save();
                    $remainingCredit -= $take;
                }

                $this->audit->write(
                    'supplier_settlement.posted',
                    $settlement,
                    $actor,
                    null,
                    [
                        'supplier_id' => $supplierId,
                        'amount' => $cash,
                        'credit_applied' => $applied,
                        'bill_ids' => $bills->pluck('id')->all(),
                    ],
                    $request,
                );

                return ['status' => 201, 'data' => $this->settlementPayload($settlement)];
            },
        );
    }

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    public function receiveRefund(User $actor, string $supplierReturnId, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'supplier_refund.create',
            $key,
            $hash,
            function () use ($actor, $supplierReturnId, $request) {
                $return = SupplierReturn::query()->whereKey($supplierReturnId)->lockForUpdate()->first();

                if ($return === null) {
                    throw new LedgerException('invalid-state', 'That supplier return does not exist.', 422);
                }

                $receivable = SupplierRefundReceivable::query()
                    ->where('supplier_return_id', $return->id)
                    ->lockForUpdate()
                    ->first();

                if ($receivable === null || $receivable->status !== RefundReceivableStatus::Open) {
                    throw new LedgerException('invalid-state', 'This supplier return has no open refund receivable.');
                }

                $entry = CashEntry::query()->create([
                    'location' => CashLocation::Bank,
                    'direction' => CashDirection::In,
                    'type' => CashEntryType::SupplierRefundIn,
                    'amount' => $receivable->amount,
                    'source_type' => $receivable->getMorphClass(),
                    'source_id' => $receivable->id,
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                ]);

                $before = $receivable->status->label();
                $receivable->status = RefundReceivableStatus::Received;
                $receivable->cash_entry_id = $entry->id;
                $receivable->save();

                $this->audit->write(
                    'supplier_refund.posted',
                    $receivable,
                    $actor,
                    ['status' => $before],
                    ['status' => $receivable->status->label(), 'amount' => $receivable->amount],
                    $request,
                );

                return ['status' => 201, 'data' => $this->returnPayload($return)];
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementPayload(SupplierSettlement $settlement): array
    {
        $settlement->load(['supplier', 'bills']);

        return SupplierSettlementResource::make($settlement)->resolve(request());
    }

    /**
     * @return array<string, mixed>
     */
    private function returnPayload(SupplierReturn $return): array
    {
        $return->load(SupplierReturn::apiRelations());

        return SupplierReturnResource::make($return)->resolve(request());
    }
}
