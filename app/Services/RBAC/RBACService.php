<?php

namespace App\Services\RBAC;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RBACService
{
    private const CACHE_TTL = 3600; // 1 hour

    // ─── Public API ────────────────────────────────────────────────────────────

    /**
     * Check if user has a specific permission in an organization.
     * Resolution order: User override → Role permissions → Parent role permissions.
     */
    public function hasPermission(User $user, string $orgId, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $allPermissions = $this->resolvePermissions($user, $orgId);

        return isset($allPermissions[$permission]) && $allPermissions[$permission] === true;
    }

    /**
     * Check multiple permissions at once.
     */
    public function hasAnyPermission(User $user, string $orgId, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $orgId, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all resolved permission slugs for a user in an org.
     */
    public function getUserPermissions(User $user, string $orgId): array
    {
        return array_keys(array_filter($this->resolvePermissions($user, $orgId)));
    }

    /**
     * Flush cached permissions for user in org.
     */
    public function flushCache(string $userId, string $orgId): void
    {
        Cache::forget($this->cacheKey($userId, $orgId));
    }

    // ─── Internal ──────────────────────────────────────────────────────────────

    /**
     * Resolve all permissions for a user — cached per user+org.
     * Returns ['permission.slug' => bool (true=granted, false=denied)]
     */
    private function resolvePermissions(User $user, string $orgId): array
    {
        return Cache::remember(
            $this->cacheKey($user->id, $orgId),
            self::CACHE_TTL,
            function () use ($user, $orgId) {
                return $this->buildPermissionMap($user, $orgId);
            }
        );
    }

    private function buildPermissionMap(User $user, string $orgId): array
    {
        $map = [];

        // 1. Role permissions (including hierarchy)
        $roles = $user->rolesInOrg($orgId)->load([
            'permissions',
            'parentRole.permissions',
            'parentRole.parentRole.permissions', // 3 levels deep
        ]);

        foreach ($roles as $role) {
            $rolePermissions = $role->resolvePermissions();
            foreach ($rolePermissions as $perm) {
                $slug = $perm->slug;
                $granted = (bool) ($perm->pivot->is_granted ?? true);

                // Only set if not already present (closest role wins)
                if (!isset($map[$slug])) {
                    $map[$slug] = $granted;
                }
            }
        }

        // 2. User direct overrides (highest priority — always overwrite)
        $directPerms = $user->directPermissionsInOrg($orgId)->load('permissions');
        foreach ($user->directPermissions()->wherePivot('organization_id', $orgId)->get() as $perm) {
            $map[$perm->slug] = (bool) $perm->pivot->is_granted;
        }

        return $map;
    }

    private function cacheKey(string $userId, string $orgId): string
    {
        return "erp:permissions:{$orgId}:{$userId}";
    }
}
