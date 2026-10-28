<?php

namespace App\Models;

use App\Enums\LotOwnership;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferLineLot extends Model
{
    use HasUuids;

    protected $fillable = [
        'transfer_line_id',
        'source_lot_id',
        'destination_lot_id',
        'supplier_id',
        'ownership',
        'unit_cost',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ownership' => LotOwnership::class,
            'unit_cost' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TransferLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(TransferLine::class, 'transfer_line_id');
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'source_lot_id');
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function destinationLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'destination_lot_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
