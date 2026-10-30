<?php

namespace App\Actions;

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Enums\CustomerReturnStatus;
use App\Enums\LotOwnership;
use App\Enums\MovementType;
use App\Enums\PointEntryType;
use App\Enums\ReturnReason;
use App\Enums\SupplierReturnStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\CustomerReturnResource;
use App\Models\CashEntry;
use App\Models\CustomerReturn;
use App\Models\SaleLine;
use App\Models\SaleLineAllocation;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ConsignmentPayables;
use App\Services\IdempotencyStore;
use App\Services\PointLedger;
use App\Services\StockLedger;
use App\Support\LargestRemainder;
use Illuminate\Http\Request;

final class RefundCustomer
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly PointLedger $points,
        private readonly ConsignmentPayables $payables,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    public function __invoke(User $actor, CustomerReturn $return, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'customer_return.refund',
            $key,
            $hash,
            function () use ($actor, $return, $key, $request) {
                $refunded = $this->refund($actor, $return, $key, $request);

                return ['status' => 200, 'data' => $this->payload($refunded)];
            },
        );
    }

    private function refund(User $actor, CustomerReturn $return, string $key, ?Request $request): CustomerReturn
    {
        $locked = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
        $locked->load('sale.customer', 'lines');

        if ($locked->status !== CustomerReturnStatus::Approved) {
            throw new LedgerException('invalid-state', 'Only an approved customer return can be refunded.');
        }

        $this->assertPin($actor, $locked->sale->warehouse_id);

        $cash = 0;
        $restored = 0;
        $clawed = 0;

        foreach ($locked->lines as $returnLine) {
            $line = SaleLine::query()->whereKey($returnLine->sale_line_id)->lockForUpdate()->firstOrFail();
            $quantity = $returnLine->quantity;

            if ($line->quantity_returned + $quantity > $line->quantity) {
                throw new LedgerException('invalid-state', 'Return quantity exceeds the units still sold.');
            }

            $returnLine->cash_amount = LargestRemainder::cumulativeSlice($line->cash_paid, $line->quantity, $line->quantity_returned, $quantity);
            $returnLine->points_restored = LargestRemainder::cumulativeSlice($line->points_redeemed, $line->quantity, $line->quantity_returned, $quantity);
            $returnLine->points_clawed_back = LargestRemainder::cumulativeSlice($line->points_earned, $line->quantity, $line->quantity_returned, $quantity);
            $returnLine->save();

            $cash += $returnLine->cash_amount;
            $restored += $returnLine->points_restored;
            $clawed += $returnLine->points_clawed_back;

            $this->applyStock($locked, $line, $returnLine->reason, $quantity, $actor, $key);

            $line->quantity_returned += $quantity;
            $line->save();
        }

        if ($cash > 0) {
            CashEntry::query()->create([
                'location' => CashLocation::Till,
                'direction' => CashDirection::Out,
                'type' => CashEntryType::CustomerRefundOut,
                'amount' => $cash,
                'source_type' => $locked->getMorphClass(),
                'source_id' => $locked->id,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
            ]);
        }

        if ($locked->sale->customer !== null) {
            $this->points->post($locked->sale->customer, PointEntryType::RedeemReversal, $restored, $locked, $actor);
            $this->points->post($locked->sale->customer, PointEntryType::EarnReversal, -$clawed, $locked, $actor);
        }

        $locked->status = CustomerReturnStatus::Refunded;
        $locked->save();

        $this->audit->write('customer_return.refunded', $locked, $actor, ['status' => 'approved'], [
            'status' => 'refunded',
            'cash_amount' => $cash,
        ], $request);

        return $locked;
    }

    private function applyStock(
        CustomerReturn $return,
        SaleLine $line,
        ReturnReason $reason,
        int $quantity,
        User $actor,
        string $key,
    ): void {
        $allocations = SaleLineAllocation::query()
            ->where('sale_line_id', $line->id)
            ->with('lot')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $skip = $line->quantity_returned;
        $need = $quantity;

        foreach ($allocations as $allocation) {
            $available = $allocation->quantity;

            if ($skip >= $available) {
                $skip -= $available;

                continue;
            }

            $available -= $skip;
            $skip = 0;
            $take = min($need, $available);

            if ($reason === ReturnReason::Resaleable) {
                $this->ledger->restoreToLot(
                    $allocation->lot,
                    $take,
                    MovementType::CustomerReturnResaleable,
                    $return,
                    $actor,
                    $key,
                );

                if ($allocation->ownership === LotOwnership::Consignment) {
                    $this->payables->credit($allocation, $allocation->unit_cost * $take);
                }
            } else {
                $this->ledger->markDamaged($allocation->lot, $take, $return, $actor, $key);

                SupplierReturn::query()->create([
                    'stock_lot_id' => $allocation->stock_lot_id,
                    'quantity' => $take,
                    'unit_cost' => $allocation->unit_cost,
                    'customer_return_id' => $return->id,
                    'sale_line_allocation_id' => $allocation->id,
                    'status' => SupplierReturnStatus::Draft,
                ]);
            }

            $need -= $take;

            if ($need === 0) {
                break;
            }
        }

        if ($need !== 0) {
            throw new LedgerException('invalid-state', 'The sold lots cannot cover this return.');
        }
    }

    private function assertPin(User $actor, string $warehouseId): void
    {
        if ($actor->warehouse_id !== null && $actor->warehouse_id !== $warehouseId) {
            throw new LedgerException('forbidden', 'You can only refund sales from your pinned warehouse.', 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CustomerReturn $return): array
    {
        $return->load(CustomerReturn::apiRelations());

        return CustomerReturnResource::make($return)->resolve(request());
    }
}
