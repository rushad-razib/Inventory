<?php

namespace App\Actions;

use App\Enums\LotOwnership;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierBillStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use App\Services\ProcurementCatalog;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ReceiveGoods
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly ProcurementCatalog $catalog,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{purchase_order_id?: string|null, supplier_id?: string|null, warehouse_id?: string|null, lines: list<array{product_id: string, quantity: int}>}  $input
     * @return array{status: int, data: array<string, mixed>}
     */
    public function __invoke(User $actor, array $input, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'goods_receipt.create',
            $key,
            $hash,
            function () use ($actor, $input, $key, $request) {
                $lines = collect($input['lines'])->sortBy('product_id')->values();

                $receipt = filled($input['purchase_order_id'] ?? null)
                    ? $this->receiveOutright($actor, (string) $input['purchase_order_id'], $lines, $key)
                    : $this->receiveConsignment($actor, $input, $lines, $key);

                $this->audit->write(
                    'goods_receipt.posted',
                    $receipt,
                    $actor,
                    null,
                    [
                        'purchase_order_id' => $receipt->purchase_order_id,
                        'warehouse_id' => $receipt->warehouse_id,
                    ],
                    $request,
                );

                return ['status' => 201, 'data' => $this->payload($receipt)];
            },
        );
    }

    /**
     * @param  Collection<int, array{product_id: string, quantity: int}>  $lines
     */
    private function receiveOutright(User $actor, string $purchaseOrderId, Collection $lines, string $key): GoodsReceipt
    {
        $locked = PurchaseOrder::query()->whereKey($purchaseOrderId)->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw new LedgerException('invalid-state', 'Only an approved purchase order can be received.');
        }

        $this->assertPin($actor, $locked->destination_warehouse_id);

        $receipt = GoodsReceipt::query()->create([
            'purchase_order_id' => $locked->id,
            'supplier_id' => $locked->supplier_id,
            'warehouse_id' => $locked->destination_warehouse_id,
            'actor_id' => $actor->id,
        ]);

        $orderLines = $locked->lines()->get()->keyBy('product_id');
        $total = 0;

        foreach ($lines as $line) {
            $orderLine = $orderLines->get($line['product_id']);

            if (! $orderLine instanceof PurchaseOrderLine) {
                throw new LedgerException('invalid-state', 'That product is not on this purchase order.', 422);
            }

            $quantity = (int) $line['quantity'];
            $remaining = $orderLine->quantity_ordered - $orderLine->quantity_received;

            if ($quantity > $remaining) {
                throw new LedgerException('invalid-state', 'Received quantity exceeds the remaining order quantity.');
            }

            $product = $this->catalog->trackedProduct($orderLine->product_id);

            $this->ledger->receiveLot(
                $locked->destination_warehouse_id,
                $product->id,
                $locked->supplier_id,
                $receipt->id,
                LotOwnership::Owned,
                $orderLine->unit_cost,
                $quantity,
                $receipt,
                $actor,
                $key,
            );

            $orderLine->quantity_received += $quantity;
            $orderLine->save();

            $receipt->lines()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $orderLine->unit_cost,
            ]);

            $total += $quantity * $orderLine->unit_cost;
        }

        $supplier = $locked->supplier()->firstOrFail();

        SupplierBill::query()->create([
            'supplier_id' => $supplier->id,
            'source_type' => $receipt->getMorphClass(),
            'source_id' => $receipt->id,
            'status' => SupplierBillStatus::Open,
            'amount' => $total,
            'amount_paid' => 0,
            'due_on' => now()->addDays((int) $supplier->payment_terms_days)->toDateString(),
        ]);

        $locked->load('lines');
        $complete = $locked->lines->every(
            fn (PurchaseOrderLine $line): bool => $line->quantity_received >= $line->quantity_ordered,
        );
        $locked->status = $complete
            ? PurchaseOrderStatus::Received
            : PurchaseOrderStatus::PartiallyReceived;
        $locked->save();

        return $receipt;
    }

    /**
     * @param  array{purchase_order_id?: string|null, supplier_id?: string|null, warehouse_id?: string|null, lines: list<array{product_id: string, quantity: int}>}  $input
     * @param  Collection<int, array{product_id: string, quantity: int}>  $lines
     */
    private function receiveConsignment(User $actor, array $input, Collection $lines, string $key): GoodsReceipt
    {
        $supplier = $this->catalog->consignmentSupplier((string) $input['supplier_id']);
        $warehouseId = (string) $input['warehouse_id'];
        $this->assertPin($actor, $warehouseId);
        $quote = $this->catalog->quote($supplier->id, $lines->all());

        $receipt = GoodsReceipt::query()->create([
            'purchase_order_id' => null,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouseId,
            'actor_id' => $actor->id,
        ]);

        foreach ($quote['lines'] as $line) {
            $this->ledger->receiveLot(
                $warehouseId,
                $line['product_id'],
                $supplier->id,
                $receipt->id,
                LotOwnership::Consignment,
                $line['unit_cost'],
                $line['quantity'],
                $receipt,
                $actor,
                $key,
            );

            $receipt->lines()->create([
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
            ]);
        }

        return $receipt;
    }

    private function assertPin(User $actor, string $warehouseId): void
    {
        if ($actor->warehouse_id !== null && $actor->warehouse_id !== $warehouseId) {
            throw new LedgerException(
                'forbidden',
                'You can only receive into your pinned warehouse.',
                403,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GoodsReceipt $receipt): array
    {
        $receipt->load(GoodsReceipt::apiRelations());

        return GoodsReceiptResource::make($receipt)->resolve(request());
    }
}
