<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationPlan;

class BillingService
{
    /**
     * Check if org can add more users.
     */
    public function canAddUser(Organization $organization): bool
    {
        $plan = $organization->plan;

        if (!$plan || $plan->isExpired()) {
            return false;
        }

        $currentUsers = $organization->users()->wherePivot('status', 'active')->count();

        return $currentUsers < $plan->max_users;
    }

    /**
     * Check if org can activate more modules.
     */
    public function canActivateModule(Organization $organization): bool
    {
        $plan = $organization->plan;

        if (!$plan || $plan->isExpired()) {
            return false;
        }

        $activeModules = $organization->activeModules()->count();

        return $activeModules < $plan->max_modules;
    }

    /**
     * Get remaining user slots.
     */
    public function remainingUserSlots(Organization $organization): int
    {
        $plan = $organization->plan;
        if (!$plan) {
            return 0;
        }
        $current = $organization->users()->wherePivot('status', 'active')->count();
        return max(0, $plan->max_users - $current);
    }

    /**
     * Get remaining module slots.
     */
    public function remainingModuleSlots(Organization $organization): int
    {
        $plan = $organization->plan;
        if (!$plan) {
            return 0;
        }
        $active = $organization->activeModules()->count();
        return max(0, $plan->max_modules - $active);
    }
}
