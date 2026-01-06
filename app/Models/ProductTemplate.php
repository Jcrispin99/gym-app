<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductTemplate extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'product_templates';

    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productProducts(): HasMany
    {
        return $this->hasMany(ProductProduct::class, 'product_template_id')->orderBy('is_principal', 'desc');
    }

    // Alias para mantener compatibilidad
    public function variants(): HasMany
    {
        return $this->productProducts();
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function mainImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->oldestOfMany();
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function image(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->mainImage ? Storage::url($this->mainImage->path) : null,
        );
    }

    public function sku(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->variants->first()?->sku,
        );
    }

    public function barcode(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->variants->first()?->barcode,
        );
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCategory($query)
    {
        return $query->with('category');
    }

    public function scopeWithVariants($query)
    {
        return $query->with('variants');
    }

    public function scopeWithImages($query)
    {
        return $query->with('images');
    }

    // ========================================
    // HELPERS
    // ========================================

    public function getPrincipalVariant()
    {
        return $this->variants()->where('is_principal', true)->first();
    }

    public function getTotalStock(): int
    {
        return $this->productProducts()->sum('stock');
    }

    // ========================================
    // ACTIVITY LOG
    // ========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'description',
                'price',
                'category_id',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
