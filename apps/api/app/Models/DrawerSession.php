<?php

namespace App\Models;

use App\Enums\CashDirection;
use App\Enums\CashLocation;
use App\Enums\DrawerStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrawerSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'cashier_id',
        'warehouse_id',
        'status',
        'opening_amount',
        'counted_amount',
        'expected_amount',
        'variance',
        'opened_at',
        'closed_at',
        'closed_by',
        'open_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DrawerStatus::class,
            'opening_amount' => 'integer',
            'counted_amount' => 'integer',
            'expected_amount' => 'integer',
            'variance' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
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
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<CashEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CashEntry::class);
    }

    public static function openFor(User $cashier): ?self
    {
        return static::query()
            ->where('cashier_id', $cashier->id)
            ->where('status', DrawerStatus::Open)
            ->first();
    }

    public function balance(): int
    {
        $total = 0;

        foreach ($this->entries()->where('location', CashLocation::Drawer)->get(['direction', 'amount']) as $entry) {
            $direction = $entry->direction instanceof CashDirection
                ? $entry->direction
                : CashDirection::from((int) $entry->direction);
            $total += ($direction === CashDirection::In ? 1 : -1) * (int) $entry->amount;
        }

        return $total;
    }
}
