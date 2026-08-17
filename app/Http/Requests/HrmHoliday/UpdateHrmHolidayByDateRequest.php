<?php

namespace App\Http\Requests\HrmHoliday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmHolidayByDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'                 => 'required|date_format:Y-m-d|after_or_equal:today',
            'name'                 => 'nullable|string|max:255',
            'uuid'                 => 'nullable|string|exists:hrm_holidays,uuid',
            'is_active'            => 'nullable|boolean',
            'holidays'             => 'nullable|array',
            'holidays.*.uuid'      => 'nullable|string|exists:hrm_holidays,uuid',
            'holidays.*.name'      => 'required_with:holidays|string|max:255',
            'holidays.*.is_active' => 'nullable|boolean',
        ];
    }
}
