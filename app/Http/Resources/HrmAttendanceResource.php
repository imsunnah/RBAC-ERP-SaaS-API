<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrmAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'           => $this->uuid,
            'biometric_id'   => $this->biometric_id,
            'employee'       => $this->whenLoaded('employee', function () {
                return [
                    'uuid'             => $this->employee->uuid,
                    'full_name'        => $this->employee->full_name,
                    'user_employee_id' => $this->employee->user_employee_id,
                    'profile_picture'  => $this->employee->profile_picture,
                    'designation'      => $this->employee->designation?->name,
                    'department'       => $this->employee->department?->name,
                ];
            }),
            'shift'          => $this->whenLoaded('shift', function () {
                return [
                    'name'         => $this->shift->name,
                    'start_time'   => $this->shift->start_time,
                    'end_time'     => $this->shift->end_time,
                    'working_days' => $this->shift->working_days,
                ];
            }),
            'date'           => $this->date ? $this->date->format('Y-m-d') : null,
            'check_in'       => $this->check_in ? $this->check_in->format('h:i A') : '-',
            'check_out'      => $this->check_out ? $this->check_out->format('h:i A') : '-',
            'working_hours'  => $this->working_hours,
            'status'         => $this->status,
            'created_at'     => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
