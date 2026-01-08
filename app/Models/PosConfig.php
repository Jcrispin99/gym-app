<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PosConfig extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'name',
        'warehouse_id',
        'default_customer_id',
        'tax_id',
        'apply_tax',
        'prices_include_tax',
        'is_active',
    ];

    protected $casts = [
        'apply_tax' => 'boolean',
        'prices_include_tax' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function defaultCustomer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'default_customer_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * Journals asociados a este POS (relación muchos a muchos)
     */
    public function journals(): BelongsToMany
    {
        return $this->belongsToMany(Journal::class, 'journal_pos_config')
            ->withPivot(['document_type', 'is_default'])
            ->withTimestamps();
    }

    /**
     * Obtener todos los journals de un tipo específico
     */
    public function getJournalsForType(string $documentType)
    {
        return $this->journals()
            ->wherePivot('document_type', $documentType)
            ->get();
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ========================================
    // ACTIVITY LOG
    // ========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'warehouse_id',
                'default_customer_id',
                'tax_id',
                'apply_tax',
                'prices_include_tax',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
