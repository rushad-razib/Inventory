<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupplierSettlement extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'cash_entry_id',
        'amount',
        'actor_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<CashEntry, $this>
     */
    public function cashEntry(): BelongsTo
    {
        return $this->belongsTo(CashEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsToMany<SupplierBill, $this>
     */
    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(SupplierBill::class, 'supplier_settlement_bills')
            ->withPivot('amount');
    }
}
