<?php

namespace App\Models;

use App\Enums\LotOwnership;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SaleLineAllocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'sale_line_id',
        'stock_lot_id',
        'quantity',
        'unit_cost',
        'ownership',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'ownership' => LotOwnership::class,
        ];
    }

    /**
     * @return BelongsTo<SaleLine, $this>
     */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    /**
     * @return MorphOne<SupplierBill, $this>
     */
    public function bill(): MorphOne
    {
        return $this->morphOne(SupplierBill::class, 'source');
    }
}
