<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PosSession extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'pos_config_id',
        'opening_balance',
        'closing_balance',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public const STATUS_OPENING_CONTROL = 'opening_control';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLOSING_CONTROL = 'closing_control';
    public const STATUS_CLOSED = 'closed';

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posConfig(): BelongsTo
    {
        return $this->belongsTo(PosConfig::class);
    }

    // ========================================
    // HELPERS
    // ========================================

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPENING_CONTROL, self::STATUS_OPENED, self::STATUS_CLOSING_CONTROL]);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    // ========================================
    // ACTIVITY LOG
    // ========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'user_id',
                'pos_config_id',
                'opening_balance',
                'closing_balance',
                'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
