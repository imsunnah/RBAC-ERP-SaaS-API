<?php

namespace App\Models\Traits;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Add the global scope so every query automatically filters by company_id
        static::addGlobalScope(new CompanyScope);

        // Automatically inject the company_id upon creating a new record
        static::creating(function ($model) {
            if (!$model->company_id) {
                try {
                    $currentCompany = app('current_company');
                    if ($currentCompany) {
                        $model->company_id = $currentCompany->id;
                    }
                } catch (\Exception $e) {
                    // Context not set
                }
            }
        });
    }

    /**
     * Relationship back to the Company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
