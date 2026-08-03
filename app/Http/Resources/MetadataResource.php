<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetadataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_null($this->resource)) {
            return [];
        }

        return [
            'id'         => $this->uuid,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
