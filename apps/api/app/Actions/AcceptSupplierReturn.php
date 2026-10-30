<?php

namespace App\Actions;

use App\Enums\LotOwnership;
use App\Enums\RefundReceivableStatus;
use App\Enums\SupplierBillStatus;
use App\Enums\SupplierReturnStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\SupplierReturnResource;
use App\Models\CreditNote;
use App\Models\GoodsReceipt;
use App\Models\SaleLineAllocation;
use App\Models\StockLot;
use App\Models\SupplierBill;
use App\Models\SupplierRefundReceivable;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use App\Services\StockLedger;
use Illuminate\Http\Request;

final class AcceptSupplierReturn
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    public function __invoke(User $actor, SupplierReturn $return, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'supplier_return.accept:'.$return->id,
            $key,
            $hash,
            function () use ($actor, $return, $key, $request) {
                $locked = SupplierReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== SupplierReturnStatus::Shipped) {
                    throw new LedgerException('invalid-state', 'Only a shipped supplier return can be accepted.');
                }

                $lot = StockLot::query()->whereKey($locked->stock_lot_id)->firstOrFail();
                $claim = $locked->quantity * $locked->unit_cost;
                $bill = $this->findBill($locked, $lot);
                $payableCredit = 0;
                $noteAmount = 0;
                $receivableAmount = 0;

                if ($bill !== null) {
                    [$payableCredit, $noteAmount, $receivableAmount] = $this->applyClaim($locked, $bill, $claim, $actor);
                } elseif ($lot->ownership === LotOwnership::Owned) {
                    throw new LedgerException('invalid-state', 'This owned lot has no supplier bill.');
                }

                $this->ledger->releaseDamaged($lot, $locked->quantity, $locked, $actor, $key);

                $locked->credited_amount = $payableCredit;
                $locked->status = SupplierReturnStatus::Accepted;
                $locked->save();

                $this->audit->write(
                    'supplier_return.accepted',
                    $locked,
                    $actor,
                    ['status' => SupplierReturnStatus::Shipped->label()],
                    [
                        'status' => SupplierReturnStatus::Accepted->label(),
                        'credited_payable' => $payableCredit,
                        'credit_note_amount' => $noteAmount,
                        'receivable_amount' => $receivableAmount,
                    ],
                    $request,
                );

                return ['status' => 200, 'data' => $this->payload($locked)];
            },
        );
    }

    private function findBill(SupplierReturn $return, StockLot $lot): ?SupplierBill
    {
        if ($return->sale_line_allocation_id !== null) {
            $allocation = SaleLineAllocation::query()->find($return->sale_line_allocation_id);

            if ($allocation !== null && $allocation->ownership === LotOwnership::Consignment) {
                $bill = SupplierBill::query()
                    ->where('source_type', $allocation->getMorphClass())
                    ->where('source_id', $allocation->id)
                    ->first();

                if ($bill === null) {
                    throw new LedgerException('invalid-state', 'This consignment sale has no payable.');
                }

                return $bill;
            }
        }

        if ($lot->ownership === LotOwnership::Consignment) {
            return null;
        }

        if ($lot->goods_receipt_id === null) {
            return null;
        }

        return SupplierBill::query()
            ->where('source_type', (new GoodsReceipt)->getMorphClass())
            ->where('source_id', $lot->goods_receipt_id)
            ->first();
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function applyClaim(SupplierReturn $return, SupplierBill $bill, int $claim, User $actor): array
    {
        $locked = SupplierBill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
        $credit = min($claim, max($locked->remainder(), 0));
        $excess = $claim - $credit;
        $payableCredit = 0;
        $noteAmount = 0;

        if ($credit > 0) {
            $locked->credited_amount += $credit;
            $isPayable = $locked->source_type === (new SaleLineAllocation)->getMorphClass();

            if ($isPayable) {
                if ($locked->remainder() <= 0) {
                    $locked->status = SupplierBillStatus::Credited;
                }

                $payableCredit = $credit;
            } else {
                $locked->status = $locked->remainder() <= 0
                    ? SupplierBillStatus::Settled
                    : SupplierBillStatus::Credited;

                CreditNote::query()->create([
                    'supplier_bill_id' => $locked->id,
                    'supplier_return_id' => $return->id,
                    'amount' => $credit,
                    'actor_id' => $actor->id,
                ]);
                $noteAmount = $credit;
            }

            $locked->save();
        }

        $receivableAmount = min($excess, $locked->amount_paid);

        if ($receivableAmount > 0) {
            SupplierRefundReceivable::query()->create([
                'supplier_id' => $locked->supplier_id,
                'supplier_return_id' => $return->id,
                'amount' => $receivableAmount,
                'status' => RefundReceivableStatus::Open,
            ]);
        }

        return [$payableCredit, $noteAmount, $receivableAmount];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SupplierReturn $return): array
    {
        $return->load(SupplierReturn::apiRelations());

        return SupplierReturnResource::make($return)->resolve(request());
    }
}
