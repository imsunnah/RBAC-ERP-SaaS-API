<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->uuid,
            'name'  => $this->name,
            'slug'  => $this->slug,
            'is_active' => true, // Defaulting for now if not in DB, but I added it to fillable
            'created_at' => $this->created_at,
        ];
    }
}
