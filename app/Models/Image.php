<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    protected $fillable = [
        'path',
        'size',
        'imageable_id',
        'imageable_type',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
