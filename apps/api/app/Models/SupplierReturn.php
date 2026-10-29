<?php

namespace App\Models;

use App\Enums\SupplierReturnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturn extends Model
{
    use HasUuids;

    protected $fillable = [
        'stock_lot_id',
        'quantity',
        'unit_cost',
        'customer_return_id',
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
}
