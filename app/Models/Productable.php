<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Productable extends Model
{
    protected $fillable = [
        'product_product_id',
        'productable_type',
        'productable_id',
        'quantity',
        'price',
        'subtotal',
        'tax_id',
        'tax_rate',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function productProduct(): BelongsTo
    {
        return $this->belongsTo(ProductProduct::class);
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
