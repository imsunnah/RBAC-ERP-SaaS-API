<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'team_name' => $this->team_name,
            'team_lead_name' => $this->team_lead_name,
            'hrm_employee_id' => $this->employee?->uuid,
            'active_status' => $this->active_status,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->uuid,
                'full_name' => $this->employee->full_name,
            ] : null),
            'team_chd' => $this->whenLoaded('teamChd', fn () => $this->teamChd->map(fn ($child) => [
                'id' => $child->uuid,
                'department_id' => $child->department?->uuid,
                'department' => $child->department ? [
                    'id' => $child->department->uuid,
                    'name' => $child->department->name,
                ] : null,
                'active_status' => $child->active_status,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}