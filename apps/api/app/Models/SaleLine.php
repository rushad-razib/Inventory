<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'shelf_total',
        'points_redeemed',
        'cash_paid',
        'points_earned',
        'quantity_returned',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'shelf_total' => 'integer',
            'points_redeemed' => 'integer',
            'cash_paid' => 'integer',
            'points_earned' => 'integer',
            'quantity_returned' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<SaleLineAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SaleLineAllocation::class)->orderBy('id');
    }
}
