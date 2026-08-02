<?php

namespace App\Http\Requests\HrmHoliday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'sometimes|required|string|max:255',
            'date'      => 'sometimes|required|date',
            'is_active' => 'nullable|boolean',
        ];
    }
}
