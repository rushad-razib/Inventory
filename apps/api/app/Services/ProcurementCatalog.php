<?php

namespace App\Services;

use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use App\Exceptions\LedgerException;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;

final class ProcurementCatalog
{
    public function outrightSupplier(string $supplierId): Supplier
    {
        return $this->supplier($supplierId, SupplyMode::Outright, 'Purchase orders require an outright supplier.');
    }

    public function consignmentSupplier(string $supplierId): Supplier
    {
        return $this->supplier($supplierId, SupplyMode::Consignment, 'Consignment receipts require a consignment supplier.');
    }

    public function agreedCost(string $supplierId, string $productId): int
    {
        $row = SupplierProduct::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->first();

        if ($row === null) {
            throw new LedgerException(
                'invalid-state',
                'This product has no agreed cost for the supplier.',
                422,
            );
        }

        return (int) $row->agreed_unit_cost;
    }

    public function trackedProduct(string $productId): Product
    {
        $product = Product::query()->findOrFail($productId);

        if (! $product->track_stock) {
            throw new LedgerException('untracked-product', 'This product does not track stock.', 422);
        }

        if (! $product->is_active) {
            throw new LedgerException('invalid-state', 'This product is not active.', 422);
        }

        return $product;
    }

    /**
     * @param  list<array{product_id: string, quantity: int}>  $lines
     * @return array{lines: list<array{product_id: string, quantity: int, unit_cost: int}>, subtotal: int, tax: int, total: int}
     */
    public function quote(string $supplierId, array $lines): array
    {
        $quoted = [];
        $subtotal = 0;

        foreach ($lines as $line) {
            $product = $this->trackedProduct($line['product_id']);
            $unitCost = $this->agreedCost($supplierId, $product->id);
            $quantity = (int) $line['quantity'];
            $quoted[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ];
            $subtotal += $quantity * $unitCost;
        }

        return [
            'lines' => $quoted,
            'subtotal' => $subtotal,
            'tax' => 0,
            'total' => $subtotal,
        ];
    }

    private function supplier(string $supplierId, SupplyMode $mode, string $modeMessage): Supplier
    {
        $supplier = Supplier::query()->findOrFail($supplierId);

        if ($supplier->status !== SupplierStatus::Active) {
            throw new LedgerException('invalid-state', 'The supplier is not active.', 422);
        }

        if ($supplier->supply_mode !== $mode) {
            throw new LedgerException('invalid-state', $modeMessage, 422);
        }

        return $supplier;
    }
}
