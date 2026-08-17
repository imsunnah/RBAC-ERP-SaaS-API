<?php

namespace App\Http\Resources\Module;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'route' => $this->route,
            'url' => $this->url,
            'is_core' => $this->is_core,
            'sort_order' => $this->sort_order,
            'url' => $this->url,
            // If pivoting relation is loaded on the org, we can show it
            'pivot' => $this->whenPivotLoaded('organization_modules', function () {
                return [
                    'is_active' => $this->pivot->is_active,
                    'activated_at' => $this->pivot->activated_at,
                    'expires_at' => $this->pivot->expires_at,
                ];
            }),
            'organizations' => $this->whenLoaded('organizations', function () {
                return $this->organizations->map(fn($org) => [
                    'id' => $org->uuid,
                    'name' => $org->name,
                    'slug' => $org->slug,
                ]);
            }),
        ];
    }
}
