<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply scope if an organization context is currently set
        try {
            $currentOrg = app('current_organization');
            if ($currentOrg) {
                // Ensure every query on this model strictly limits data to the current organization's UUID
                $builder->where($model->getTable() . '.organization_id', $currentOrg->id);
            }
        } catch (\Exception $e) {
            // Context not set during system boots or CLI commands without explicit setup
        }
    }
}
