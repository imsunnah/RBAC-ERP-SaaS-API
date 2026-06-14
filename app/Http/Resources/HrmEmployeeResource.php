<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrmEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'user_employee_id' => $this->user_employee_id,
            'full_name' => $this->full_name,
            'office_email' => $this->office_email,
            'office_mobile_no' => $this->office_mobile_no,
            'department_id' => $this->department_id,
            'designation_id' => $this->designation_id,
            'is_active' => $this->is_active,
            'hire_date' => $this->hire_date ? $this->hire_date->format('Y-m-d') : null,
            'profile_picture' => $this->profile_picture,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
