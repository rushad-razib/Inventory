<?php

namespace App\Models;

use App\Enums\SupplierBillStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupplierBill extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'source_type',
        'source_id',
        'status',
        'amount',
        'amount_paid',
        'credited_amount',
        'due_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierBillStatus::class,
            'amount' => 'integer',
            'amount_paid' => 'integer',
            'credited_amount' => 'integer',
            'due_on' => 'date',
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
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
