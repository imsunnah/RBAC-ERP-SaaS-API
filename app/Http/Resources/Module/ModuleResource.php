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
            'is_core' => $this->is_core,
            'sort_order' => $this->sort_order,
            // If pivoting relation is loaded on the org, we can show it
            'pivot' => $this->whenPivotLoaded('organization_modules', function () {
                return [
                    'is_active' => $this->pivot->is_active,
                    'activated_at' => $this->pivot->activated_at,
                    'expires_at' => $this->pivot->expires_at,
                ];
            }),
        ];
    }
}
