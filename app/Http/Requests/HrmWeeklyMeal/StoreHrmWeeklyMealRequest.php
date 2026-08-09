<?php

namespace App\Http\Requests\HrmWeeklyMeal;

use Illuminate\Foundation\Http\FormRequest;

class StoreHrmWeeklyMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->has('meals') && is_array($this->input('meals'))) {
            return [
                'meals'                     => 'required|array|min:1',
                'meals.*.day'               => 'required|string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,saturday,sunday,monday,tuesday,wednesday,thursday,friday',
                'meals.*.lunch_item'        => 'required|string|max:255',
                'meals.*.alternative_lunch' => 'nullable|string|max:255',
                'meals.*.expected_person'   => 'nullable|integer|min:0',
                'meals.*.chef_id'           => 'nullable|string',
                'meals.*.chef_name'         => 'nullable|string|max:255',
                'meals.*.is_active'         => 'nullable|boolean',
            ];
        }

        return [
            'day'               => 'required|string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'lunch_item'        => 'required|string|max:255',
            'alternative_lunch' => 'nullable|string|max:255',
            'expected_person'   => 'nullable|integer|min:0',
            'chef_id'           => 'nullable|string',
            'chef_name'         => 'nullable|string|max:255',
            'is_active'         => 'nullable|boolean',
        ];
    }
}
