<?php

namespace App\Services\Sidebar;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SidebarService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Build the sidebar for a user in an organization.
     * Only returns modules active for org, menus allowed by role, submenus allowed by role.
     */
    public function build(User $user, Organization $organization): array
    {
        $cacheKey = $this->cacheKey($user->id, $organization->id);

        // Super-admin always gets fresh uncached sidebar or full set
        if ($user->is_super_admin) {
            return $this->buildForSuperAdmin($organization);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $organization) {
            return $this->buildForUser($user, $organization);
        });
    }

    public function flushCache(string $userId, string $orgId): void
    {
        Cache::forget($this->cacheKey($userId, $orgId));
    }

    // ─── Builders ──────────────────────────────────────────────────────────────

    private function buildForUser(User $user, Organization $organization): array
    {
        // Gather role IDs for user in org
        $roleIds = $user->roles()
            ->wherePivot('organization_id', $organization->id)
            ->pluck('roles.id');

        // Active module IDs for the organization
        $activeModuleIds = $organization->activeModules()->pluck('modules.id')->toArray();

        // Get all Module IDs specifically granted to any of the user's Roles
        $roleModuleIds = \Illuminate\Support\Facades\DB::table('role_modules')
            ->whereIn('role_id', $roleIds)
            ->pluck('module_id')
            ->toArray();

        // The user can only see modules that both the Org has active, AND the Role has access to
        $allowedModuleIds = array_intersect($activeModuleIds, $roleModuleIds);

        // Menus and Submenus are dynamically loaded entirely based on the allowed Modules
        $menus = \App\Models\Menu::query()
            ->whereIn('module_id', $allowedModuleIds)
            ->where('is_active', true)
            ->with([
                'module:id,uuid,name,slug,icon',
                'submenus' => fn($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get();

        return $this->formatSidebar($menus);
    }

    private function buildForSuperAdmin(Organization $organization): array
    {
        $activeModuleIds = $organization->activeModules()->pluck('modules.id');

        $menus = \App\Models\Menu::query()
            ->whereHas('module', fn($q) => $q->whereIn('id', $activeModuleIds)->where('is_active', true))
            ->where('is_active', true)
            ->with([
                'module:id,uuid,name,slug,icon',
                'submenus' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get();

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

    private function cacheKey(string $userId, string $orgId): string
    {
        return "erp:sidebar:{$orgId}:{$userId}";
    }
}
