<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\LedgerException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProcurementCatalog;
use Illuminate\Support\Facades\DB;

final class CreatePurchaseOrdersFromAlerts
{
    public function __construct(private readonly ProcurementCatalog $catalog) {}

    /**
     * @param  array{lines: list<array{warehouse_id: string, product_id: string}>}  $input
     * @return list<PurchaseOrder>
     */
    public function __invoke(User $actor, array $input): array
    {
        return DB::transaction(function () use ($actor, $input) {
            $groups = [];
            $seen = [];

            foreach ($input['lines'] as $line) {
                $pair = $line['warehouse_id'].'|'.$line['product_id'];

                if (isset($seen[$pair])) {
                    throw new LedgerException('invalid-state', 'Each warehouse and product can be selected once.', 422);
                }

                $seen[$pair] = true;
                $product = Product::query()->with('preferredSupplier')->findOrFail($line['product_id']);
                $warehouse = Warehouse::query()->findOrFail($line['warehouse_id']);

                if (! $product->track_stock || ! $product->is_active || $product->reorder_point <= 0) {
                    throw new LedgerException('invalid-state', 'That product is not on the reorder list.', 422);
                }

                $onHand = (int) (StockBalance::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->value('on_hand') ?? 0);

                if ($onHand > $product->reorder_point) {
                    throw new LedgerException('invalid-state', 'That product is not below its reorder point.', 422);
                }

                $supplier = $product->preferredSupplier;

                if ($supplier === null) {
                    throw new LedgerException('invalid-state', 'That product has no preferred supplier.', 422);
                }

                $this->catalog->outrightSupplier($supplier->id);
                $unitCost = $this->catalog->agreedCost($supplier->id, $product->id);
                $quantity = $product->reorder_quantity > 0
                    ? $product->reorder_quantity
                    : max($product->reorder_point - $onHand, 1);

                $key = $supplier->id.'|'.$warehouse->id;
                $groups[$key]['supplier_id'] = $supplier->id;
                $groups[$key]['warehouse_id'] = $warehouse->id;
                $groups[$key]['lines'][] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ];
            }

            $orders = [];

            foreach ($groups as $group) {
                $subtotal = 0;

                foreach ($group['lines'] as $quoted) {
                    $subtotal += $quoted['quantity'] * $quoted['unit_cost'];
                }

                $order = PurchaseOrder::query()->create([
                    'number' => PurchaseOrder::nextNumber(),
                    'supplier_id' => $group['supplier_id'],
                    'destination_warehouse_id' => $group['warehouse_id'],
                    'status' => PurchaseOrderStatus::Draft,
                    'requester_id' => $actor->id,
                    'subtotal' => $subtotal,
                    'tax' => 0,
                    'total' => $subtotal,
                ]);

                foreach ($group['lines'] as $quoted) {
                    $order->lines()->create([
                        'product_id' => $quoted['product_id'],
                        'quantity_ordered' => $quoted['quantity'],
                        'quantity_received' => 0,
                        'unit_cost' => $quoted['unit_cost'],
                    ]);
                }

                $orders[] = $order->load(PurchaseOrder::apiRelations());
            }

            return $orders;
        });
    }
}
