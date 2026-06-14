<?php

namespace App\Http\Resources\Sidebar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'route' => $this->route,
            'url' => $this->url,
            'sort_order' => $this->sort_order,
            'is_active' => (bool)$this->is_active,
            'permission_id' => $this->when($this->permission_id, fn() => $this->permission?->uuid),
            'menu' => $this->whenLoaded('menu', fn() => [
                'id' => $this->menu->uuid,
                'name' => $this->menu->name,
                'slug' => $this->menu->slug,
            ]),
            'permission' => $this->whenLoaded('permission', fn() => [
                'id' => $this->permission->uuid,
                'name' => $this->permission->name,
                'slug' => $this->permission->slug,
            ]),
        ];
    }
}
