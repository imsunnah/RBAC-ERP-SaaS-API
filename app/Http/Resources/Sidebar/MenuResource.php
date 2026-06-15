<?php

namespace App\Http\Resources\Sidebar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            // 'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'route' => $this->route,
            'url' => $this->url,
            'sort_order' => $this->sort_order,
            'is_active' => (bool)$this->is_active,
            'module' => $this->whenLoaded('module', fn() => [
                'id' => $this->module->uuid,
                'name' => $this->module->name,
                'slug' => $this->module->slug,
            ]),
            'submenus' => SubmenuResource::collection($this->whenLoaded('submenus')),
        ];
    }
}
