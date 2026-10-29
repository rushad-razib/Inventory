<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\LedgerException;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ProcurementCatalog;
use Illuminate\Support\Facades\DB;

final class SavePurchaseOrder
{
    public function __construct(private readonly ProcurementCatalog $catalog) {}

    /**
     * @param  array{supplier_id: string, destination_warehouse_id: string, expected_on?: string|null, notes?: string|null, lines: list<array{product_id: string, quantity: int}>}  $input
     */
    public function create(User $actor, array $input): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $input) {
            $supplier = $this->catalog->outrightSupplier($input['supplier_id']);
            $quote = $this->catalog->quote($supplier->id, $input['lines']);

            $order = PurchaseOrder::query()->create([
                'number' => PurchaseOrder::nextNumber(),
                'supplier_id' => $supplier->id,
                'destination_warehouse_id' => $input['destination_warehouse_id'],
                'status' => PurchaseOrderStatus::Draft,
                'requester_id' => $actor->id,
                'expected_on' => $input['expected_on'] ?? null,
                'notes' => $input['notes'] ?? null,
                'subtotal' => $quote['subtotal'],
                'tax' => $quote['tax'],
                'total' => $quote['total'],
            ]);

            $this->writeLines($order, $quote['lines']);

            return $order->load(PurchaseOrder::apiRelations());
        });
    }

    /**
     * @param  array{supplier_id: string, destination_warehouse_id: string, expected_on?: string|null, notes?: string|null, lines: list<array{product_id: string, quantity: int}>}  $input
     */
    public function update(PurchaseOrder $order, array $input): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $input) {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new LedgerException('invalid-state', 'Only a draft purchase order can be edited.');
            }

            $supplier = $this->catalog->outrightSupplier($input['supplier_id']);
            $quote = $this->catalog->quote($supplier->id, $input['lines']);

            $locked->fill([
                'supplier_id' => $supplier->id,
                'destination_warehouse_id' => $input['destination_warehouse_id'],
                'expected_on' => $input['expected_on'] ?? null,
                'notes' => $input['notes'] ?? null,
                'subtotal' => $quote['subtotal'],
                'tax' => $quote['tax'],
                'total' => $quote['total'],
            ])->save();

            $locked->lines()->delete();
            $this->writeLines($locked, $quote['lines']);

            return $locked->load(PurchaseOrder::apiRelations());
        });
    }

    /**
     * @param  list<array{product_id: string, quantity: int, unit_cost: int}>  $lines
     */
    private function writeLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $order->lines()->create([
                'product_id' => $line['product_id'],
                'quantity_ordered' => $line['quantity'],
                'quantity_received' => 0,
                'unit_cost' => $line['unit_cost'],
            ]);
        }
    }
}
