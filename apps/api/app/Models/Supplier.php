<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use App\Enums\SupplyMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'contact',
        'email',
        'phone',
        'address',
        'supply_mode',
        'payment_terms_days',
        'lead_time_days',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supply_mode' => SupplyMode::class,
            'status' => SupplierStatus::class,
            'payment_terms_days' => 'integer',
            'lead_time_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function preferredProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'preferred_supplier_id');
    }
}
