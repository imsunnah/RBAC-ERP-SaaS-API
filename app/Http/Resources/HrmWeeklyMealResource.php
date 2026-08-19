<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HrmWeeklyMealResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $chef = $this->relationLoaded('chef') ? $this->chef : null;
        $chefPhoto = null;
        if ($chef) {
            $photoPath = $chef->profile_picture ?? $chef->employee_photo;
            $chefPhoto = $photoPath ? asset(Storage::url($photoPath)) : null;
        }

        return [
            'id'                => $this->uuid,
            'uuid'              => $this->uuid,
            'day'               => ucfirst(strtolower($this->day)),
            'lunch_item'        => $this->lunch_item,
            'alternative_lunch' => $this->alternative_lunch,
            'expected_person'   => $this->expected_person,
            'chef_id'           => $chef ? $chef->uuid : null,
            'chef_name'         => $this->chef_name ?? ($chef ? $chef->full_name : null),
            'chef'              => $chef ? [
                'uuid'         => $chef->uuid,
                'name'         => $chef->full_name,
                'email'        => $chef->office_email,
                'employee_id'  => $chef->user_employee_id,
                'photo_url'    => $chefPhoto,
            ] : null,
            'is_active'         => $this->is_active,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
