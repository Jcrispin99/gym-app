<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'is_fiscal',
        'document_type_code',
        'sequence_id',
        'company_id',
    ];

    protected $casts = [
        'is_fiscal' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
