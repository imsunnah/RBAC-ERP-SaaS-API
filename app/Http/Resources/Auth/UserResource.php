<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'is_super_admin' => $this->is_super_admin,
            'organizations' => $this->whenLoaded('organizations', function () {
                return $this->organizations->map(fn($org) => [
                    // 'id' => $org->id,
                    'uuid' => $org->uuid,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'logo' => $org->logo ?? null,
                    'company' => $org->company ? [
                        'uuid' => $org->company->uuid,
                        'name' => $org->company->name,
                        'slug' => $org->company->slug,
                    ] : null,
                    'modules' => $org->modules->map(fn($module) => [
                        'uuid' => $module->uuid,
                        'name' => $module->name,
                        'slug' => $module->slug,
                        'menus' => $module->menus->map(fn($menu) => [
                            'uuid' => $menu->uuid,
                            'name' => $menu->name,
                            'slug' => $menu->slug,
                            'submenus' => $menu->submenus->map(fn($submenu) => [
                                'uuid' => $submenu->uuid,
                                'name' => $submenu->name,
                                'slug' => $submenu->slug,
                            ])
                        ])
                    ])
                ]);
            }),
        ];
    }
}
