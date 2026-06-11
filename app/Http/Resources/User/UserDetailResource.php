<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->uuid,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'full_name'    => collect([$this->first_name, $this->last_name])->filter()->join(' '),
            'email'        => $this->email,
            'phone'        => $this->phone,
            'status'       => $this->status,
            'is_super_admin' => $this->is_super_admin,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
            'roles'        => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($r) => [
                    'id'      => $r->uuid,
                    'name'    => $r->name,
                    'slug'    => $r->slug,
                    'level'   => $r->level,
                    'modules' => $r->relationLoaded('modules')
                        ? $r->modules->map(fn($m) => [
                            'id'   => $m->uuid,
                            'slug' => $m->slug,
                            'name' => $m->name,
                        ])->values()
                        : [],
                ]);
            }),
        ];
    }
}
