<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Bypass if user is super admin
        $user = Auth::user();
        if ($user && $user->is_super_admin) {
            return;
        }

        try {
            $currentCompany = app('current_company');
            if ($currentCompany) {
                $builder->where($model->getTable() . '.company_id', $currentCompany->id);
            }
        } catch (\Exception $e) {
            // Context not set
        }
    }
}
