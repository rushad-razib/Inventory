<?php

namespace App\Models;

use App\Enums\CustomerReturnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerReturn extends Model
{
    use HasUuids;

    protected $fillable = [
        'sale_id',
        'requester_id',
        'approver_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerReturnStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return HasMany<CustomerReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerReturnLine::class);
    }

    /**
     * @return HasMany<SupplierReturn, $this>
     */
    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }

    /**
     * @return list<string>
     */
    public static function apiRelations(): array
    {
        return [
            'sale.warehouse',
            'requester',
            'approver',
            'lines.saleLine.product',
            'supplierReturns',
        ];
    }
}
