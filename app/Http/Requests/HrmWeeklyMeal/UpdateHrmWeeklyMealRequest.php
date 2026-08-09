<?php

namespace App\Http\Requests\HrmWeeklyMeal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmWeeklyMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day'               => 'sometimes|required|string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'lunch_item'        => 'sometimes|required|string|max:255',
            'alternative_lunch' => 'nullable|string|max:255',
            'expected_person'   => 'nullable|integer|min:0',
            'chef_id'           => 'nullable|string',
            'chef_name'         => 'nullable|string|max:255',
            'is_active'         => 'nullable|boolean',
        ];
    }
}
