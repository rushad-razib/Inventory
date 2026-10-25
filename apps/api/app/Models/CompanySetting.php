<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'currency',
        'po_approval_threshold',
        'adjustment_approval_threshold',
        'points_earn_per',
        'point_redeem_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'po_approval_threshold' => 'integer',
            'adjustment_approval_threshold' => 'integer',
            'points_earn_per' => 'integer',
            'point_redeem_value' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
