<?php

namespace App\Models;

use App\Enums\SupplierReturnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierReturn extends Model
{
    use HasUuids;

    protected $fillable = [
        'stock_lot_id',
        'quantity',
        'unit_cost',
        'credited_amount',
        'customer_return_id',
        'sale_line_allocation_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'credited_amount' => 'integer',
            'status' => SupplierReturnStatus::class,
        ];
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    /**
     * @return BelongsTo<CustomerReturn, $this>
     */
    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    /**
     * @return BelongsTo<SaleLineAllocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(SaleLineAllocation::class, 'sale_line_allocation_id');
    }

    /**
     * @return HasOne<CreditNote, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class);
    }

    /**
     * @return HasOne<SupplierRefundReceivable, $this>
     */
    public function refundReceivable(): HasOne
    {
        return $this->hasOne(SupplierRefundReceivable::class);
    }

    /**
     * @return list<string>
     */
    public static function apiRelations(): array
    {
        return [
            'lot.product',
            'lot.warehouse',
            'lot.supplier',
            'creditNote',
            'refundReceivable',
        ];
    }
}
