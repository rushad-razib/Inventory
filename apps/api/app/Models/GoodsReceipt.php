<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class GoodsReceipt extends Model
{
    use HasUuids;

    /**
     * @return list<string>
     */
    public static function apiRelations(): array
    {
        return [
            'supplier',
            'warehouse',
            'actor',
            'purchaseOrder',
            'lines.product',
            'lots',
            'bill',
        ];
    }

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'warehouse_id',
        'actor_id',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /**
     * @return HasMany<StockLot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    /**
     * @return MorphOne<SupplierBill, $this>
     */
    public function bill(): MorphOne
    {
        return $this->morphOne(SupplierBill::class, 'source');
    }
}
