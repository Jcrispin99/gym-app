<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, LogsActivity, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'user_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the company that owns the user (legacy - single company)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the companies that the user belongs to (many-to-many)
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withTimestamps();
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'company_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the partner (customer/provider) associated with this user
     */
    public function partner()
    {
        return $this->hasOne(Partner::class);
    }

    // ========================================
    // TYPE CHECKERS
    // ========================================
    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->user_type === 'staff';
    }

    /**
     * Check if user is customer
     */
    public function isCustomer(): bool
    {
        return $this->user_type === 'customer';
    }

    /**
     * Check if user is provider
     */
    public function isProvider(): bool
    {
        return $this->user_type === 'provider';
    }

    // ========================================
    // SCOPES
    // ========================================
    /**
     * Scope to filter only staff users
     */
    public function scopeStaff($query)
    {
        return $query->where('user_type', 'staff');
    }

    /**
     * Scope to filter only customer users
     */
    public function scopeCustomers($query)
    {
        return $query->where('user_type', 'customer');
    }

    /**
     * Scope to filter only provider users
     */
    public function scopeProviders($query)
    {
        return $query->where('user_type', 'provider');
    }
}
