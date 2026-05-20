<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->uuid,
            'department_id'          => $this->department_id,
            'department_name'          => $this->department_name,
            'department_slug'          => $this->department_slug,
            'department_abbr'          => $this->department_abbr,
            'active_status'          => $this->active_status,
            'parent_department_id'          => $this->parent_department_id,
            'user_defined_sl_no'          => $this->user_defined_sl_no,
            'department_title'          => $this->department_title,
            'is_client'          => $this->is_client,
            'record_show_flag'          => $this->record_show_flag,
            'su_organization_id'          => $this->su_organization_id,
            'branch_id'          => $this->branch_id,
        ];
    }
}
