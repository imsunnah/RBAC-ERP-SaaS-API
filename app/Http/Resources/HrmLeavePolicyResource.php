<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrmLeavePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $updater = $this->relationLoaded('updater') ? $this->updater : null;

        return [
            'id' => $this->uuid,
            'uuid' => $this->uuid,
            'leave_type' => new MetadataResource($this->whenLoaded('leaveType')),
            'employee_type' => $this->relationLoaded('employeeType') && $this->employeeType
                ? new MetadataResource($this->employeeType)
                : null,
            'eligibility' => $this->employeeType?->name ?? 'All Type Employee',
            'leave_balance' => (float) $this->leave_balance,
            'last_updated_by' => $updater?->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
