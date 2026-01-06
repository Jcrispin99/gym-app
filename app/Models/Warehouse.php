<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'location',
        'company_id',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function posConfigs(): HasMany
    {
        return $this->hasMany(PosConfig::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
