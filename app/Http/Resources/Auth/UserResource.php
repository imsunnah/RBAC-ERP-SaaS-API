<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSuperAdmin = (bool) $this->is_super_admin;

        return [
            'uuid' => $this->uuid,
            'name' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'employee_uuid' => $this->employee_id ? $this->employee?->uuid : null,
            'is_super_admin' => $isSuperAdmin,
            'organizations' => $this->whenLoaded('organizations', function () use ($isSuperAdmin) {
                return $this->organizations->map(function($org) use ($isSuperAdmin) {
                    
                    $allowedModuleIds = [];
                    $allowedMenuIds = [];
                    $allowedSubmenuIds = [];

                    if (!$isSuperAdmin) {
                        // Resolve roles and their associated modules, menus, and submenus for this specific organization
                        $roles = $this->rolesInOrg($org->id)->load(['modules', 'menus', 'submenus']);
                        $allowedModuleIds = $roles->flatMap->modules->pluck('id')->unique()->toArray();
                        $allowedMenuIds = $roles->flatMap->menus->pluck('id')->unique()->toArray();
                        $allowedSubmenuIds = $roles->flatMap->submenus->pluck('id')->unique()->toArray();

                        // Include directly assigned modules for the user in this organization
                        $directModuleIds = $this->modulesInOrg($org->id)->pluck('modules.id')->toArray();
                        $allowedModuleIds = array_values(array_unique(array_merge($allowedModuleIds, $directModuleIds)));
                    }

                    $modules = $org->modules;
                    if (!$isSuperAdmin) {
                        $modules = $modules->filter(fn($module) => in_array($module->id, $allowedModuleIds));
                    }

                    return [
                        'uuid' => $org->uuid,
                        'name' => $org->name,
                        'url' => $org->url,
                        'logo' => $org->logo ?? null,
                        'company' => $org->company ? [
                            'uuid' => $org->company->uuid,
                            'name' => $org->company->name,
                            'url' => $org->company->url,
                        ] : null,
                        'modules' => $modules->map(function($module) use ($isSuperAdmin, $allowedMenuIds, $allowedSubmenuIds) {
                            $menus = $module->menus;
                            if (!$isSuperAdmin) {
                                $menus = $menus->filter(fn($menu) => in_array($menu->id, $allowedMenuIds));
                            }

                            return [
                                'uuid' => $module->uuid,
                                'name' => $module->name,
                                'url' => $module->url,
                                'menus' => $menus->map(function($menu) use ($isSuperAdmin, $allowedSubmenuIds) {
                                    $submenus = $menu->submenus;
                                    if (!$isSuperAdmin) {
                                        $submenus = $submenus->filter(fn($submenu) => in_array($submenu->id, $allowedSubmenuIds));
                                    }

                                    return [
                                        'uuid' => $menu->uuid,
                                        'name' => $menu->name,
                                        'url' => $menu->url,
                                        'submenus' => $submenus->map(fn($submenu) => [
                                            'uuid' => $submenu->uuid,
                                            'name' => $submenu->name,
                                            'url' => $submenu->url,
                                        ])->values()->toArray(),
                                    ];
                                })->values()->toArray(),
                            ];
                        })->values()->toArray(),
                    ];
                });
            }),
        ];
    }
}
