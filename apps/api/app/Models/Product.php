<?php

namespace App\Models;

use App\Services\ImageService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::deleting(function (Product $product): void {
            app(ImageService::class)->deleteAllFor($product);
        });
    }

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'description',
        'category_id',
        'unit_of_measure',
        'sell_price',
        'reorder_point',
        'reorder_quantity',
        'preferred_supplier_id',
        'track_stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sell_price' => 'integer',
            'reorder_point' => 'integer',
            'reorder_quantity' => 'integer',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('sort_order');
    }
}
