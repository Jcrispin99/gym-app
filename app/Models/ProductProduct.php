<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductProduct extends Model
{
    use LogsActivity;

    protected $table = 'product_products';

    protected $fillable = [
        'product_template_id',
        'sku',
        'barcode',
        'price',
        'cost_price',
        'is_principal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_principal' => 'boolean',
    ];

    // ========================================
    // BOOT & EVENTS
    // ========================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($productProduct) {
            // Auto-generar barcode si está vacío
            if (empty($productProduct->barcode)) {
                $productProduct->barcode = static::generateUniqueBarcode();
            }
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'product_template_id');
    }

    // Alias para mantener compatibilidad
    public function productTemplate(): BelongsTo
    {
        return $this->product();
    }

    // Alias 'template' para uso en vistas
    public function template(): BelongsTo
    {
        return $this->product();
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_product', 'product_product_id');
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'product_product_id');
    }

    public function productables(): HasMany
    {
        return $this->hasMany(Productable::class, 'product_product_id');
    }

    // ========================================
    // ACCESSORS & HELPERS
    // ========================================

    /**
     * Get stock from latest inventory balance for default warehouse
     */
    public function getStockAttribute(): int
    {
        // Obtener stock del último balance de inventario
        $latestInventory = $this->inventories()
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestInventory ? $latestInventory->quantity_balance : 0;
    }

    /**
     * Get stock for specific warehouse
     */
    public function getStockInWarehouse($warehouseId): int
    {
        $latestInventory = $this->inventories()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestInventory ? $latestInventory->quantity_balance : 0;
    }

    public function getDisplayNameAttribute(): string
    {
        $productName = $this->product->name ?? 'Producto';
        $attributes = $this->attributeValues->pluck('value')->join(' - ');

        return $attributes ? "{$productName} - {$attributes}" : $productName;
    }

    public function getAttributeStringAttribute(): string
    {
        return $this->attributeValues->pluck('value')->join(', ') ?: 'Sin atributos';
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopePrincipal($query)
    {
        return $query->where('is_principal', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeWithProduct($query)
    {
        return $query->with('product');
    }

    public function scopeWithAttributes($query)
    {
        return $query->with('attributeValues.attribute');
    }

    // ========================================
    // ACTIVITY LOG
    // ========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'product_template_id',
                'sku',
                'barcode',
                'price',
                'is_principal',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ========================================
    // BARCODE GENERATION
    // ========================================

    /**
     * Genera un código de barras único tipo EAN-13
     */
    public static function generateUniqueBarcode(): string
    {
        do {
            $barcode = static::generateEAN13();
        } while (static::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Genera un código EAN-13 válido con checksum
     * Formato: 13 dígitos con dígito verificador
     */
    private static function generateEAN13(): string
    {
        // Prefijo personalizado (2 dígitos) - puedes usar el código de tu país o uno custom
        $prefix = '77'; // Custom prefix

        // Generar 10 dígitos aleatorios
        $randomDigits = '';
        for ($i = 0; $i < 10; $i++) {
            $randomDigits .= rand(0, 9);
        }

        $barcode = $prefix.$randomDigits;

        // Calcular dígito verificador (checksum)
        $checksum = static::calculateEAN13Checksum($barcode);

        return $barcode.$checksum;
    }

    /**
     * Calcula el dígito verificador para EAN-13
     */
    private static function calculateEAN13Checksum(string $barcode): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $barcode[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $checksum = (10 - ($sum % 10)) % 10;

        return $checksum;
    }
}
