<?php

namespace App\Models;

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'location',
        'direction',
        'type',
        'amount',
        'source_type',
        'source_id',
        'actor_id',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => CashLocation::class,
            'direction' => CashDirection::class,
            'type' => CashEntryType::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
