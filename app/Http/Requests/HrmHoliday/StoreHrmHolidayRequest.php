<?php

namespace App\Http\Requests\HrmHoliday;

use Illuminate\Foundation\Http\FormRequest;

class StoreHrmHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'date'      => 'required|date_format:Y-m-d',
            'is_active' => 'nullable|boolean',
        ];
    }
}
