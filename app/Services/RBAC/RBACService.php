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
     * Flush cached permissions for user in org by incrementing its version.
     */
    public function flushCache(string $userId, string $orgId): void
    {
        $this->bumpCacheVersion($userId, $orgId);
    }

    /**
     * Increment the cache version for a specific user in an organization.
     */
    public function bumpCacheVersion(string $userId, string $orgId): void
    {
        Cache::increment($this->versionKey($userId, $orgId));
    }

    // ─── Internal ──────────────────────────────────────────────────────────────

    /**
     * Resolve all permissions for a user — cached per user+org+version.
     */
    private function resolvePermissions(User $user, string $orgId): array
    {
        $version = Cache::get($this->versionKey($user->id, $orgId), 1);

        return Cache::remember(
            $this->cacheKey($user->id, $orgId, $version),
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
        $roles = $user->rolesInOrg($orgId);

        $roles->load([
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

        // 2. Direct module assignments (grant all permissions of directly assigned modules by default)
        $directModuleIds = \Illuminate\Support\Facades\DB::table('user_modules')
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->pluck('module_id')
            ->toArray();

        if (!empty($directModuleIds)) {
            $modulePermissions = \App\Models\Permission::whereIn('module_id', $directModuleIds)->get();
            foreach ($modulePermissions as $perm) {
                $map[$perm->slug] = true;
            }
        }

        // 3. User direct overrides (highest priority — always overwrite)
        foreach ($user->directPermissions()->wherePivot('organization_id', $orgId)->get() as $perm) {
            $map[$perm->slug] = (bool) $perm->pivot->is_granted;
        }

        return $map;
    }

    /**
     * Get authorized module/menu/submenu hierarchy for user in org.
     */
    public function getUserMenus(User $user, string $orgId): array
    {
        if ($user->is_super_admin) {
            return \App\Models\Module::with(['menus' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }, 'menus.submenus' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])->where('is_active', true)->orderBy('sort_order')->get()->toArray();
        }

        // Use cached full hierarchy if possible, but keep it simple for now
        // Resolution order should match buildPermissionMap

        // 1. Resolve authorized IDs from roles and direct assignments
        $roles = $user->rolesInOrg($orgId)->load(['modules', 'menus', 'submenus']);

        $moduleIds = collect();
        $menuIds = collect();
        $submenuIds = collect();

        foreach ($roles as $role) {
            $moduleIds = $moduleIds->merge($role->modules->pluck('id'));
            $menuIds = $menuIds->merge($role->menus->pluck('id'));
            $submenuIds = $submenuIds->merge($role->submenus->pluck('id'));
        }

        // 2. Direct module assignments
        $directModuleIds = \Illuminate\Support\Facades\DB::table('user_modules')
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->pluck('module_id');
        $moduleIds = $moduleIds->merge($directModuleIds)->unique();

        // 3. Fetch full hierarchy filtered by authorized IDs
        return \App\Models\Module::with([
            'menus' => function ($q) use ($menuIds) {
                $q->whereIn('id', $menuIds)->where('is_active', true)->orderBy('sort_order');
            },
            'menus.submenus' => function ($q) use ($submenuIds) {
                $q->whereIn('id', $submenuIds)->where('is_active', true)->orderBy('sort_order');
            }
        ])
            ->whereIn('id', $moduleIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    private function versionKey(string $userId, string $orgId): string
    {
        return "erp:perms:v:{$orgId}:{$userId}";
    }

    private function cacheKey(string $userId, string $orgId, int $version): string
    {
        return "erp:perms:{$orgId}:{$userId}:v{$version}";
    }
}
