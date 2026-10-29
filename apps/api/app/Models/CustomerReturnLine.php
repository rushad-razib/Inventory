<?php

namespace App\Models;

use App\Enums\ReturnReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturnLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_return_id',
        'sale_line_id',
        'quantity',
        'reason',
        'cash_amount',
        'points_restored',
        'points_clawed_back',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reason' => ReturnReason::class,
            'cash_amount' => 'integer',
            'points_restored' => 'integer',
            'points_clawed_back' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomerReturn, $this>
     */
    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    /**
     * @return BelongsTo<SaleLine, $this>
     */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }
}
