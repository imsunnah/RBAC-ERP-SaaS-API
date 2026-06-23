<?php

namespace App\Http\Resources;

use App\Http\Resources\Sidebar\MenuResource;
use App\Http\Resources\Sidebar\SubmenuResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'role_name' => $this->name,
            'role_slug' => $this->slug,
            'role_description' => $this->description,
            'is_inheritable' => $this->is_inheritable,
            'is_active' => $this->is_active,
            'level' => $this->level,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn() =>
                $this->permissions->map(fn($p) => [
                    'id' => $p->uuid,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'is_granted' => $p->pivot->is_granted,
                ])
            ),
            'modules' => $this->whenLoaded(
                'modules',
                fn() =>
                $this->modules->map(fn($m) => [
                    'id' => $m->uuid,
                    'name' => $m->name,
                    'slug' => $m->slug,
                ])
            ),
            'menus' => $this->whenLoaded(
                'menus',
                fn() => MenuResource::collection($this->menus)
            ),
            'submenus' => $this->whenLoaded(
                'submenus',
                fn() => SubmenuResource::collection($this->submenus)
            ),
        ];
    }
}
