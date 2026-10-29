<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pointsEarned = (int) $this->lines->sum('points_earned');

        return [
            'id' => $this->id,
            'number' => $this->number,
            'warehouse_id' => $this->warehouse_id,
            'cashier_id' => $this->cashier_id,
            'customer_id' => $this->customer_id,
            'status' => $this->status->label(),
            'subtotal' => $this->subtotal,
            'points_redeemed' => $this->points_redeemed,
            'cash_and_card_paid' => $this->cash_and_card_paid,
            'total' => $this->total,
            'points_earned' => $pointsEarned,
            'points_balance_after' => $this->customer?->points_balance,
            'created_at' => $this->created_at?->toIso8601String(),
            'warehouse' => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ],
            'cashier' => [
                'id' => $this->cashier->id,
                'name' => $this->cashier->name,
            ],
            'customer' => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'mobile' => $this->customer->mobile,
                'name' => $this->customer->name,
                'points_balance' => $this->customer->points_balance,
            ],
            'lines' => $this->lines->map(fn ($line): array => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'shelf_total' => $line->shelf_total,
                'points_redeemed' => $line->points_redeemed,
                'cash_paid' => $line->cash_paid,
                'points_earned' => $line->points_earned,
                'quantity_returned' => $line->quantity_returned,
                'product' => [
                    'id' => $line->product->id,
                    'sku' => $line->product->sku,
                    'name' => $line->product->name,
                    'barcode' => $line->product->barcode,
                ],
                'allocations' => $line->allocations->map(fn ($allocation): array => [
                    'id' => $allocation->id,
                    'stock_lot_id' => $allocation->stock_lot_id,
                    'quantity' => $allocation->quantity,
                    'unit_cost' => $allocation->unit_cost,
                    'ownership' => $allocation->ownership->label(),
                    'payable' => $allocation->bill === null ? null : [
                        'id' => $allocation->bill->id,
                        'status' => $allocation->bill->status->label(),
                        'amount' => $allocation->bill->amount,
                        'credited_amount' => $allocation->bill->credited_amount,
                        'due_on' => $allocation->bill->due_on?->toDateString(),
                    ],
                ])->values()->all(),
            ])->values()->all(),
            'tenders' => $this->tenders->map(fn ($tender): array => [
                'id' => $tender->id,
                'type' => $tender->type->label(),
                'amount' => $tender->amount,
            ])->values()->all(),
        ];
    }
}
