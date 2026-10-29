<?php

namespace App\Services;

use App\Enums\LotOwnership;
use App\Enums\MovementType;
use App\Exceptions\LedgerException;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class StockLedger
{
    public function lockBalance(string $warehouseId, string $productId): StockBalance
    {
        $balance = StockBalance::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ],
            [
                'on_hand' => 0,
                'damaged' => 0,
                'in_transit' => 0,
            ],
        );

        return StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Remove sellable quantity, oldest owned lots first.
     *
     * @return list<array{lot: StockLot, quantity: int}>
     */
    public function consumeOnHand(
        string $warehouseId,
        string $productId,
        int $quantity,
        MovementType $type,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
        bool $toDamaged,
        bool $toInTransit,
    ): array {
        $balance = $this->lockBalance($warehouseId, $productId);

        $lots = StockLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('qty_on_hand', '>', 0)
            ->orderBy('ownership')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ((int) $lots->sum('qty_on_hand') < $quantity) {
            throw new LedgerException(
                'insufficient-stock',
                'Tracked stock cannot go negative.',
            );
        }

        $remaining = $quantity;
        $slices = [];

        foreach ($lots as $lot) {
            if ($remaining === 0) {
                break;
            }

            $take = min($remaining, $lot->qty_on_hand);
            $lot->qty_on_hand -= $take;

            if ($toDamaged) {
                $lot->qty_damaged += $take;
            }

            $lot->save();

            $this->movement(
                $productId,
                $warehouseId,
                $lot,
                $type,
                -$take,
                $source,
                $actor,
                $idempotencyKey,
                $toDamaged ? 'damage' : null,
            );

            $slices[] = ['lot' => $lot, 'quantity' => $take];
            $remaining -= $take;
        }

        $balance->on_hand -= $quantity;

        if ($toDamaged) {
            $balance->damaged += $quantity;
        }

        if ($toInTransit) {
            $balance->in_transit += $quantity;
        }

        $balance->save();

        return $slices;
    }

    /**
     * Put found units on hand. A warehouse with no owned lot gets an opening lot at cost 0.
     */
    public function increaseOnHand(
        string $warehouseId,
        string $productId,
        int $quantity,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
    ): StockLot {
        $balance = $this->lockBalance($warehouseId, $productId);

        $lot = StockLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('ownership', LotOwnership::Owned)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($lot === null) {
            $lot = StockLot::query()->create([
                'goods_receipt_id' => null,
                'supplier_id' => null,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'unit_cost' => 0,
                'ownership' => LotOwnership::Owned,
                'qty_received' => $quantity,
                'qty_on_hand' => $quantity,
                'qty_damaged' => 0,
                'received_at' => now(),
            ]);
        } else {
            $lot->qty_on_hand += $quantity;
            $lot->save();
        }

        $balance->on_hand += $quantity;
        $balance->save();

        $this->movement(
            $productId,
            $warehouseId,
            $lot,
            MovementType::Adjustment,
            $quantity,
            $source,
            $actor,
            $idempotencyKey,
            null,
        );

        return $lot;
    }

    /**
     * Insert a new receipt lot. Never adds quantity onto an existing lot.
     */
    public function receiveLot(
        string $warehouseId,
        string $productId,
        ?string $supplierId,
        ?string $goodsReceiptId,
        LotOwnership $ownership,
        int $unitCost,
        int $quantity,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
    ): StockLot {
        $balance = $this->lockBalance($warehouseId, $productId);

        $lot = StockLot::query()->create([
            'goods_receipt_id' => $goodsReceiptId,
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'unit_cost' => $unitCost,
            'ownership' => $ownership,
            'qty_received' => $quantity,
            'qty_on_hand' => $quantity,
            'qty_damaged' => 0,
            'received_at' => now(),
        ]);

        $balance->on_hand += $quantity;
        $balance->save();

        $this->movement(
            $productId,
            $warehouseId,
            $lot,
            MovementType::Receipt,
            $quantity,
            $source,
            $actor,
            $idempotencyKey,
            null,
        );

        return $lot;
    }

    public function receiveSlice(
        string $sourceWarehouseId,
        string $destinationWarehouseId,
        string $productId,
        ?string $supplierId,
        LotOwnership $ownership,
        int $unitCost,
        int $quantity,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
    ): StockLot {
        $sourceBalance = $this->lockBalance($sourceWarehouseId, $productId);
        $destinationBalance = $this->lockBalance($destinationWarehouseId, $productId);

        if ($sourceBalance->in_transit < $quantity) {
            throw new LedgerException(
                'invalid-state',
                'In-transit quantity is lower than the slice being received.',
            );
        }

        $destinationLot = StockLot::query()->create([
            'goods_receipt_id' => null,
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'warehouse_id' => $destinationWarehouseId,
            'unit_cost' => $unitCost,
            'ownership' => $ownership,
            'qty_received' => $quantity,
            'qty_on_hand' => $quantity,
            'qty_damaged' => 0,
            'received_at' => now(),
        ]);

        $sourceBalance->in_transit -= $quantity;
        $sourceBalance->save();

        $destinationBalance->on_hand += $quantity;
        $destinationBalance->save();

        $this->movement(
            $productId,
            $destinationWarehouseId,
            $destinationLot,
            MovementType::TransferIn,
            $quantity,
            $source,
            $actor,
            $idempotencyKey,
            null,
        );

        return $destinationLot;
    }

    public function restoreToLot(
        StockLot $lot,
        int $quantity,
        MovementType $type,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
    ): void {
        $balance = $this->lockBalance($lot->warehouse_id, $lot->product_id);
        $locked = StockLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

        $locked->qty_on_hand += $quantity;
        $locked->save();

        $balance->on_hand += $quantity;
        $balance->save();

        $this->movement(
            $locked->product_id,
            $locked->warehouse_id,
            $locked,
            $type,
            $quantity,
            $source,
            $actor,
            $idempotencyKey,
            null,
        );
    }

    public function markDamaged(
        StockLot $lot,
        int $quantity,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
    ): void {
        $balance = $this->lockBalance($lot->warehouse_id, $lot->product_id);
        $locked = StockLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

        $locked->qty_damaged += $quantity;
        $locked->save();

        $balance->damaged += $quantity;
        $balance->save();

        $this->movement(
            $locked->product_id,
            $locked->warehouse_id,
            $locked,
            MovementType::CustomerReturnDamaged,
            $quantity,
            $source,
            $actor,
            $idempotencyKey,
            'damaged',
        );
    }

    private function movement(
        string $productId,
        string $warehouseId,
        StockLot $lot,
        MovementType $type,
        int $quantity,
        Model $source,
        User $actor,
        ?string $idempotencyKey,
        ?string $notes,
    ): void {
        StockMovement::query()->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'stock_lot_id' => $lot->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $lot->unit_cost,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'idempotency_key' => $idempotencyKey,
            'actor_id' => $actor->id,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }
}
