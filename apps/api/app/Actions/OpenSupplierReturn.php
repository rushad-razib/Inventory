<?php

namespace App\Actions;

use App\Enums\SupplierReturnStatus;
use App\Exceptions\LedgerException;
use App\Models\StockLot;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OpenSupplierReturn
{
    /**
     * @param  array{stock_lot_id: string, quantity: int}  $input
     */
    public function __invoke(User $actor, array $input): SupplierReturn
    {
        return DB::transaction(function () use ($actor, $input) {
            $lot = StockLot::query()->whereKey($input['stock_lot_id'])->lockForUpdate()->firstOrFail();
            $this->assertPin($actor, $lot->warehouse_id);

            $quantity = (int) $input['quantity'];
            $reserved = (int) SupplierReturn::query()
                ->where('stock_lot_id', $lot->id)
                ->whereIn('status', [SupplierReturnStatus::Draft, SupplierReturnStatus::Shipped])
                ->lockForUpdate()
                ->sum('quantity');

            if ($quantity > $lot->qty_damaged - $reserved) {
                throw new LedgerException('invalid-state', 'That quantity is not available as unreserved damaged stock.');
            }

            return SupplierReturn::query()->create([
                'stock_lot_id' => $lot->id,
                'quantity' => $quantity,
                'unit_cost' => $lot->unit_cost,
                'credited_amount' => 0,
                'status' => SupplierReturnStatus::Draft,
            ]);
        });
    }

    private function assertPin(User $actor, string $warehouseId): void
    {
        if ($actor->warehouse_id !== null && $actor->warehouse_id !== $warehouseId) {
            throw new LedgerException('forbidden', 'You can only open a supplier return from your pinned warehouse.', 403);
        }
    }
}
