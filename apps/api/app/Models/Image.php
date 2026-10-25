<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'disk',
        'path',
        'mime_type',
        'byte_size',
        'sort_order',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
