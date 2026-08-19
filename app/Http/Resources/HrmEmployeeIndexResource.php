<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HrmEmployeeIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->uuid,
            'employeeId'  => $this->user_employee_id,
            'name'        => $this->full_name,
            'nativeName'  => $this->employee_name_local,
            'avatarUrl'   => $this->profile_picture
                ? asset(Storage::url($this->profile_picture))
                : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80',
            'designation' => $this->relationLoaded('designation') && $this->designation ? $this->designation->name : null,
            'workShift'   => $this->relationLoaded('workShift') && $this->workShift ? [
                'name'    => $this->workShift->name,
                'time'    => $this->workShift->start_time && $this->workShift->end_time
                    ? \Carbon\Carbon::parse($this->workShift->start_time)->format('g:i A') . '–' . \Carbon\Carbon::parse($this->workShift->end_time)->format('g:i A')
                    : null,
                'daysOff' => $this->workShift->weekend,
            ] : null,
            'department'  => $this->relationLoaded('department') && $this->department ? $this->department->name : null,
            'mobileNo'    => $this->personal_mobile_no ?? $this->office_mobile_no,
            'bloodGroup'  => $this->relationLoaded('bloodGroup') && $this->bloodGroup ? $this->bloodGroup->name : null,
            'joiningDate' => $this->start_date ? $this->start_date->format('j M Y') : ($this->hire_date ? $this->hire_date->format('j M Y') : null),
            'status'      => $this->relationLoaded('employeeType') && $this->employeeType ? $this->employeeType->name : 'Permanent',
        ];
    }
}
