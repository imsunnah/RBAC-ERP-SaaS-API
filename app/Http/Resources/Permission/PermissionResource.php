<?php

namespace App\Http\Resources\Permission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'group' => $this->group,
            'module' => $this->whenLoaded('module', fn() => [
                'id' => $this->module->uuid,
                'name' => $this->module->name,
                'slug' => $this->module->slug,
            ]),
        ];
    }
}
