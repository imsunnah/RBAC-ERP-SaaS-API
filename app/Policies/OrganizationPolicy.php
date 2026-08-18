<?php

namespace App\Policies;

use App\Models\User;
use App\Services\RBAC\RBACService;

/**
 * Base policy that delegates authorization to the RBACService.
 *
 * All model policies extend this and call checkPermission()
 * with the appropriate permission slug.
 */
abstract class OrganizationPolicy
{
    public function __construct(protected RBACService $rbacService) {}

    /**
     * Check a permission slug against the current organization context.
     */
    protected function checkPermission(User $user, string $slug): bool
    {
        // Super admins always pass
        if ($user->is_super_admin) {
            return true;
        }

        try {
            $org = app()->bound('current_organization') ? app('current_organization') : null;
        } catch (\Exception $e) {
            return false;
        }

        if (!$org) {
            return false;
        }

        return $this->rbacService->hasPermission($user, $org->id, $slug);
    }
}
