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
                    'url' => $org->url,
                    'logo' => $org->logo ?? null,
                    'company' => $org->company ? [
                        'uuid' => $org->company->uuid,
                        'name' => $org->company->name,
                        'url' => $org->company->url,
                    ] : null,
                    'modules' => $org->modules->map(fn($module) => [
                        'uuid' => $module->uuid,
                        'name' => $module->name,
                        'url' => $module->url,
                        'menus' => $module->menus->map(fn($menu) => [
                            'uuid' => $menu->uuid,
                            'name' => $menu->name,
                            'url' => $menu->url,
                            'submenus' => $menu->submenus->map(fn($submenu) => [
                                'uuid' => $submenu->uuid,
                                'name' => $submenu->name,
                                'url' => $submenu->url,
                            ]),
                        ]),
                    ]),
                ]);
            }),
        ];
    }
}
