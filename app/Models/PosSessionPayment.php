<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PosSessionPayment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'pos_session_id',
        'sale_id',
        'payment_method_id',
        'amount',
        'reference_sale_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The credit note sale referenced by this payment (if applicable)
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'reference_sale_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // ========================================
    // ACTIVITY LOG
    // ========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_method_id', 'amount', 'sale_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
