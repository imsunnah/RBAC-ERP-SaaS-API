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
            'employee' => $this->whenLoaded('employee'),
            'punch_time' => $this->punch_time->format('Y-m-d H:i:s'),
            'punch_state' => $this->punch_state,
            'punch_type' => $this->punch_type,
            'device_ip' => $this->device_ip,
            'synced_at' => $this->synced_at ? $this->synced_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
