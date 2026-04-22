<?php

namespace App\Services\Sidebar;

use App\Models\User;
use App\Models\Organization;
use App\Services\RBAC\RBACService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SidebarService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(private RBACService $rbacService)
    {
    }

    /**
     * Build the sidebar for a user in an organization.
     * Optionally filter by a specific module slug.
     */
    public function build(User $user, Organization $organization, ?string $moduleSlug = null): array
    {
        $cacheKey = $this->cacheKey($user->id, $organization->id, $moduleSlug);

        // Super-admin always gets full sidebar (no role filtering)
        if ($user->is_super_admin) {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organization, $moduleSlug) {
                return $this->buildForSuperAdmin($organization, $moduleSlug);
            });
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $organization, $moduleSlug) {
            return $this->buildForUser($user, $organization, $moduleSlug);
        });
    }

    public function flushCache(string $userId, string $orgId): void
    {
        // Flush all possible cache keys (with and without module filter)
        Cache::forget($this->cacheKey($userId, $orgId));
        // Note: module-specific caches will expire naturally
    }

    // ─── Builders ──────────────────────────────────────────────────────────────

    private function buildForUser(User $user, Organization $organization, ?string $moduleSlug = null): array
    {
        // Gather role IDs for user in org
        $roleIds = $user->roles()
            ->wherePivot('organization_id', $organization->id)
            ->pluck('roles.id');

        // Active module IDs for the organization
        $activeModuleIds = $organization->activeModules()->pluck('modules.id')->toArray();

        // Module IDs specifically granted to any of the user's Roles
        $roleModuleIds = DB::table('role_modules')
            ->whereIn('role_id', $roleIds)
            ->pluck('module_id')
            ->toArray();

        // Intersection: org has module active AND role has access
        $allowedModuleIds = array_intersect($activeModuleIds, $roleModuleIds);

        // Menu IDs the role has access to via role_menus pivot
        $allowedMenuIds = DB::table('role_menus')
            ->whereIn('role_id', $roleIds)
            ->pluck('menu_id')
            ->unique()
            ->toArray();

        // Submenu IDs the role has access to via role_submenus pivot
        $allowedSubmenuIds = DB::table('role_submenus')
            ->whereIn('role_id', $roleIds)
            ->pluck('submenu_id')
            ->unique()
            ->toArray();

        // Resolve all user permissions for submenu permission_id check
        $grantedPermissionSlugs = $this->rbacService->getUserPermissions($user, $organization->id);
        $grantedPermissionIds = \App\Models\Permission::whereIn('slug', $grantedPermissionSlugs)
            ->pluck('id')
            ->toArray();

        // Build menu query
        $menuQuery = \App\Models\Menu::query()
            ->whereIn('module_id', $allowedModuleIds)
            ->whereIn('id', $allowedMenuIds) // ← Filter by role_menus
            ->where('is_active', true)
            ->with([
                'module:id,uuid,name,slug,icon',
                'submenus' => fn($q) => $q
                    ->where('is_active', true)
                    ->where(function ($sq) use ($allowedSubmenuIds, $grantedPermissionIds) {
                        $sq->whereIn('id', $allowedSubmenuIds) // Must be in role_submenus
                           ->where(function ($pq) use ($grantedPermissionIds) {
                               // AND either has no permission requirement, or the user has it
                               $pq->whereNull('permission_id')
                                  ->orWhereIn('permission_id', $grantedPermissionIds);
                           });
                    })
                    ->orderBy('sort_order'),
            ])
            ->orderBy('sort_order');

        // Optional: filter by specific module
        if ($moduleSlug) {
            $moduleId = \App\Models\Module::where('slug', $moduleSlug)->value('id');
            if ($moduleId) {
                $menuQuery->where('module_id', $moduleId);
            }
        }

        $menus = $menuQuery->get();

        return $this->formatSidebar($menus);
    }

    private function buildForSuperAdmin(Organization $organization, ?string $moduleSlug = null): array
    {
        $activeModuleIds = $organization->activeModules()->pluck('modules.id');

        $menuQuery = \App\Models\Menu::query()
            ->whereHas('module', fn($q) => $q->whereIn('id', $activeModuleIds)->where('is_active', true))
            ->where('is_active', true)
            ->with([
                'module:id,uuid,name,slug,icon',
                'submenus' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])
            ->orderBy('sort_order');

        // Optional: filter by specific module
        if ($moduleSlug) {
            $moduleId = \App\Models\Module::where('slug', $moduleSlug)->value('id');
            if ($moduleId) {
                $menuQuery->where('module_id', $moduleId);
            }
        }

        $menus = $menuQuery->get();

        return $this->formatSidebar($menus);
    }

    private function formatSidebar(Collection $menus): array
    {
        // Group by module
        $grouped = $menus->groupBy('module.slug');

        $sidebar = [];

        foreach ($grouped as $moduleSlug => $moduleMenus) {
            $mod = $moduleMenus->first()->module;
            $sidebar[] = [
                'module' => [
                    'id' => $mod->uuid,
                    'name' => $mod->name,
                    'slug' => $mod->slug,
                    'icon' => $mod->icon,
                ],
                'menus' => $moduleMenus->map(fn($menu) => [
                    'id' => $menu->uuid,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'icon' => $menu->icon,
                    'route' => $menu->route,
                    'url' => $menu->url,
                    'submenus' => $menu->submenus->map(fn($sub) => [
                        'id' => $sub->uuid,
                        'name' => $sub->name,
                        'slug' => $sub->slug,
                        'icon' => $sub->icon,
                        'route' => $sub->route,
                        'url' => $sub->url,
                    ])->values()->toArray(),
                ])->values()->toArray(),
            ];
        }

        return $sidebar;
    }

    private function cacheKey(string $userId, string $orgId, ?string $moduleSlug = null): string
    {
        $key = "erp:sidebar:{$orgId}:{$userId}";
        if ($moduleSlug) {
            $key .= ":{$moduleSlug}";
        }
        return $key;
    }
}

