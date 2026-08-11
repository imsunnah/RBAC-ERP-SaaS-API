<?php

namespace App\Http\Requests\HrmEmployeeMealSelection;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\HrmEmployee;
use App\Models\HrmWeeklyMeal;

class StoreHrmEmployeeMealSelectionRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    protected function resolvedKeys(): array
    {
        return [
            'hrm_employee_id' => HrmEmployee::class,
            'selections.*.hrm_weekly_meal_id' => HrmWeeklyMeal::class,
            'hrm_weekly_meal_id' => HrmWeeklyMeal::class,
        ];
    }

    public function rules(): array
    {
        if ($this->has('selections') && is_array($this->input('selections'))) {
            return [
                'hrm_employee_id'               => 'required|uuid|exists:hrm_employees,uuid',
                'selections'                    => 'required|array|min:1',
                'selections.*.hrm_weekly_meal_id' => 'required|uuid|exists:hrm_weekly_meals,uuid',
                'selections.*.date'             => 'required|date',
                'selections.*.selection'        => 'required|string|in:standard,alternative,none',
            ];
        }

        return [
            'hrm_employee_id'    => 'required|uuid|exists:hrm_employees,uuid',
            'hrm_weekly_meal_id' => 'required|uuid|exists:hrm_weekly_meals,uuid',
            'date'               => 'required|date',
            'selection'          => 'required|string|in:standard,alternative,none',
        ];
    }
}
