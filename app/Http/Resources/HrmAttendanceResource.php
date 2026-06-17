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
            'uuid' => $this->uuid,
            'device_user_id' => $this->device_user_id,
            'biometric_id' => $this->biometric_id,
            'employee'       => $this->whenLoaded('employee'),
            'date'           => $this->date ? $this->date->format('Y-m-d') : null,
            'punch_in'       => $this->punch_in ? $this->punch_in->format('Y-m-d H:i:s') : null,
            'punch_out'      => $this->punch_out ? $this->punch_out->format('Y-m-d H:i:s') : null,
            'punch_state'    => $this->punch_state,
            'punch_type' => $this->punch_type,
            'device_ip' => $this->device_ip,
            'synced_at' => $this->synced_at ? $this->synced_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
