<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipFreeze extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_subscription_id',
        'freeze_start_date',
        'freeze_end_date',
        'days_frozen',
        'reason',
        'requested_by',
        'approved_by',
        'status',
    ];

    protected $casts = [
        'freeze_start_date' => 'date',
        'freeze_end_date' => 'date',
    ];

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MembershipSubscription::class, 'membership_subscription_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function complete(): void
    {
        $this->freeze_end_date = now();
        $this->days_frozen = $this->freeze_start_date->diffInDays($this->freeze_end_date);
        $this->status = 'completed';
        $this->save();

        // Extend subscription end date
        $this->subscription->extendEndDate($this->days_frozen);
        $this->subscription->total_days_frozen += $this->days_frozen;
        $this->subscription->save();
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }
}
