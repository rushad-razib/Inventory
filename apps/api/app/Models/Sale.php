<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasUuids;

    protected $fillable = [
        'number',
        'warehouse_id',
        'cashier_id',
        'customer_id',
        'status',
        'subtotal',
        'points_redeemed',
        'cash_and_card_paid',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'subtotal' => 'integer',
            'points_redeemed' => 'integer',
            'cash_and_card_paid' => 'integer',
            'total' => 'integer',
        ];
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
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /**
     * @return HasMany<Tender, $this>
     */
    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class);
    }

    /**
     * @return HasMany<CustomerReturn, $this>
     */
    public function customerReturns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    /**
     * @return list<string>
     */
    public static function apiRelations(): array
    {
        return [
            'warehouse',
            'cashier',
            'customer',
            'lines.product',
            'lines.allocations.lot',
            'lines.allocations.bill',
            'tenders',
        ];
    }

    public static function nextNumber(): string
    {
        $latest = static::query()->orderByDesc('number')->lockForUpdate()->value('number');
        $sequence = 1;

        if (is_string($latest) && preg_match('/^S-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('S-%06d', $sequence);
    }
}
