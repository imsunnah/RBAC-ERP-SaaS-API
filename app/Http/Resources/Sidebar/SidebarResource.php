<?php

namespace App\Http\Resources\Sidebar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SidebarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The service already builds the deeply nested array, so we can just return it.
        return parent::toArray($request);
    }
}
