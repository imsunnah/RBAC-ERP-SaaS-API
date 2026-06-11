<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_inheritable' => $this->is_inheritable,
            'is_active' => $this->is_active,
            'level' => $this->level,
            'parent_role' => $this->whenLoaded('parentRole', fn() => [
                'id' => $this->parentRole->uuid,
                'name' => $this->parentRole->name,
                'slug' => $this->parentRole->slug,
            ]),
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
                $this->modules->map(fn($m) => ['id' => $m->uuid, 'name' => $m->name, 'slug' => $m->slug])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
