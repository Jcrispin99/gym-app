<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToCompany()
    {
        // Apply global scope to filter by selected companies
        static::addGlobalScope('company', function (Builder $builder) {
            $companyIds = session('selected_company_ids', []);

            if (! empty($companyIds)) {
                $builder->whereIn(
                    $builder->getModel()->getTable().'.company_id',
                    $companyIds
                );
            }
        });

        // Auto-assign company_id when creating
        static::creating(function ($model) {
            if (! $model->company_id) {
                $companyIds = session('selected_company_ids', []);

                // Assign the first selected company if available
                if (! empty($companyIds)) {
                    $model->company_id = $companyIds[0];
                }
            }
        });
    }

    /**
     * Get the company that owns the model
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to filter by specific companies (useful for overriding global scope)
     */
    public function scopeForCompanies(Builder $query, array $companyIds)
    {
        return $query->withoutGlobalScope('company')
            ->whereIn($this->getTable().'.company_id', $companyIds);
    }

    /**
     * Scope to include all companies (remove company filter)
     */
    public function scopeAllCompanies(Builder $query)
    {
        return $query->withoutGlobalScope('company');
    }
}
