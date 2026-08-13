<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrmEmployeeMealSelectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'employee'            => $this->relationLoaded('employee') && $this->employee ? [
                'uuid'      => $this->employee->uuid,
                'full_name' => $this->employee->full_name,
            ] : null,
            'weekly_meal'         => $this->relationLoaded('weeklyMeal') && $this->weeklyMeal ? [
                'uuid'              => $this->weeklyMeal->uuid,
                'day'               => $this->weeklyMeal->day,
                'lunch_item'        => $this->weeklyMeal->lunch_item,
                'alternative_lunch' => $this->weeklyMeal->alternative_lunch,
                'chef_name'         => $this->weeklyMeal->chef_name,
                'chef'              => $this->weeklyMeal->relationLoaded('chef') && $this->weeklyMeal->chef ? [
                    'uuid'             => $this->weeklyMeal->chef->uuid,
                    'full_name'        => $this->weeklyMeal->chef->full_name,
                    'user_employee_id' => $this->weeklyMeal->chef->user_employee_id,
                    'profile_picture'  => $this->weeklyMeal->chef->profile_picture,
                ] : null,
            ] : null,
            'date'                => $this->date 
                ? \Carbon\Carbon::parse($this->date)->format('Y-m-d') 
                : null,
            'selection'           => $this->selection,
            'selected_item_name'  => $this->selected_item_name ?? '-',
            'is_manually_updated' => $this->is_manually_updated,
        ];
    }
}
