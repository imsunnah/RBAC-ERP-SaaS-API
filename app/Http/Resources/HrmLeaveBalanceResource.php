<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrmLeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'employee'   => $this->relationLoaded('employee') && $this->employee ? [
                'uuid'      => $this->employee->uuid,
                'full_name' => $this->employee->full_name,
            ] : null,
            'leave_type' => new MetadataResource($this->whenLoaded('leaveType')),
            'year'       => $this->year,
            'total_days' => (float) $this->total_days,
            'used_days'  => (float) $this->used_days,
            'pending_days' => (float) ($this->pending_days ?? 0),
            'remaining'  => (float) max(0, $this->total_days - $this->used_days - ($this->pending_days ?? 0)),
        ];
    }
}
