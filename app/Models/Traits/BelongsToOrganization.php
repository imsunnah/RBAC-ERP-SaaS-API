<?php

namespace App\Models\Traits;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    /**
     * The "boot" method of the trait.
     */
    public static function bootBelongsToOrganization(): void
    {
        // Add the global scope so every query automatically filters by organization_id
        static::addGlobalScope(new OrganizationScope);

        // Automatically inject the organization_id upon creating a new record
        static::creating(function ($model) {
            if (!$model->organization_id) {
                try {
                    $currentOrg = app('current_organization');
                    if ($currentOrg) {
                        $model->organization_id = $currentOrg->id;
                    }
                } catch (\Exception $e) {
                    // Context not set
                }
            }
        });
    }

    /**
     * Relationship back to the Organization.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
