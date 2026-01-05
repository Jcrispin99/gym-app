<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Partner extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'partner_type',
        
        // Documentos
        'document_type',
        'document_number',
        
        // Nombres
        'business_name',
        'first_name',
        'last_name',
        
        // Contacto
        'email',
        'phone',
        'mobile',
        'photo_url',
        
        // Dirección
        'address',
        'district',
        'province',
        'department',
        
        // Campos de customers
        'birth_date',
        'gender',
        'emergency_contact_name',
        'emergency_contact_phone',
        'blood_type',
        'medical_notes',
        'allergies',
        
        // Campos de providers
        'payment_terms',
        'credit_limit',
        'tax_id',
        'business_license',
        'provider_category',
        
        // Comunes
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'credit_limit' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the company that the partner belongs to
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user account (if partner has portal access)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the membership subscriptions for the partner.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(MembershipSubscription::class);
    }

    /**
     * Get all attendances for this partner
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the active membership subscription for the partner.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(MembershipSubscription::class)
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->latest('end_date');
    }

    // ========================================
    // TYPE CHECKERS
    // ========================================

    /**
     * Check if partner is a customer
     */
    public function isCustomer(): bool
    {
        return $this->partner_type === 'customer';
    }

    /**
     * Check if partner is a provider
     */
    public function isProvider(): bool
    {
        return $this->partner_type === 'provider';
    }

    /**
     * Check if partner is a supplier
     */
    public function isSupplier(): bool
    {
        return $this->partner_type === 'supplier';
    }

    /**
     * Check if partner has portal access
     */
    public function hasPortalAccess(): bool
    {
        return !is_null($this->user_id);
    }

    // ========================================
    // GETTERS
    // ========================================

    /**
     * Get full name (for individuals) or business name (for companies)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->business_name) {
            return $this->business_name;
        }
        
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get full name for individuals
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ========================================
    // SCOPES - BY TYPE
    // ========================================

    /**
     * Scope to filter only customers
     */
    public function scopeCustomers($query)
    {
        return $query->where('partner_type', 'customer');
    }

    /**
     * Scope to filter only providers
     */
    public function scopeProviders($query)
    {
        return $query->where('partner_type', 'provider');
    }

    /**
     * Scope to filter only suppliers
     */
    public function scopeSuppliers($query)
    {
        return $query->where('partner_type', 'supplier');
    }

    // ========================================
    // SCOPES - BY STATUS
    // ========================================

    /**
     * Scope to filter active partners
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter inactive partners
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope to filter suspended partners
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    // ========================================
    // SCOPES - BY ACCESS
    // ========================================

    /**
     * Scope to filter partners with portal access
     */
    public function scopeWithPortalAccess($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope to filter partners without portal access
     */
    public function scopeWithoutPortalAccess($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'partner_type',
                'document_number',
                'business_name',
                'first_name',
                'last_name',
                'email',
                'phone',
                'status',
                'user_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
