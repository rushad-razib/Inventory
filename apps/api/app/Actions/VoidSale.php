<?php

namespace App\Actions;

use App\Enums\MovementType;
use App\Enums\PointEntryType;
use App\Enums\SaleStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\SaleResource;
use App\Models\CashEntry;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ConsignmentPayables;
use App\Services\PointLedger;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class VoidSale
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly PointLedger $points,
        private readonly ConsignmentPayables $payables,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $actor, Sale $sale, ?Request $request): array
    {
        $voided = DB::transaction(function () use ($actor, $sale, $request): Sale {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SaleStatus::Completed) {
                throw new LedgerException('invalid-state', 'Only a completed sale can be voided.');
            }

            if ($locked->customerReturns()->exists()) {
                throw new LedgerException('invalid-state', 'A sale with a customer return cannot be voided.');
            }

            $locked->load('lines.allocations.lot', 'customer');

            foreach ($locked->lines as $line) {
                foreach ($line->allocations as $allocation) {
                    $this->ledger->restoreToLot(
                        $allocation->lot,
                        $allocation->quantity,
                        MovementType::Sale,
                        $locked,
                        $actor,
                        null,
                    );
                }
            }

            $this->payables->creditOpenBills($locked);

            if ($locked->customer !== null) {
                $earned = (int) $locked->lines->sum('points_earned');
                $this->points->post($locked->customer, PointEntryType::RedeemReversal, $locked->points_redeemed, $locked, $actor);
                $this->points->post($locked->customer, PointEntryType::EarnReversal, -$earned, $locked, $actor);
            }

            $entries = CashEntry::query()
                ->where('source_type', $locked->getMorphClass())
                ->where('source_id', $locked->id)
                ->get();

            foreach ($entries as $entry) {
                CashEntry::query()->create([
                    'location' => $entry->location,
                    'direction' => $entry->direction->opposite(),
                    'type' => $entry->type,
                    'amount' => $entry->amount,
                    'source_type' => $locked->getMorphClass(),
                    'source_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'occurred_at' => now(),
                ]);
            }

            $locked->status = SaleStatus::Voided;
            $locked->save();

            $this->audit->write('sale.voided', $locked, $actor, ['status' => 'completed'], ['status' => 'voided'], $request);

            return $locked;
        });

        $voided->load(Sale::apiRelations());

        return SaleResource::make($voided)->resolve($request);
    }
}
