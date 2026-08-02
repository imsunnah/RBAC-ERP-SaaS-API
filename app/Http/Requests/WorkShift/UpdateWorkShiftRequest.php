<?php

namespace App\Http\Requests\WorkShift;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                   => 'sometimes|required|string|max:100',
            'short_code'             => 'nullable|string|max:10',
            'start_time'             => 'sometimes|required|date_format:H:i:s',
            'end_time'               => 'sometimes|required|date_format:H:i:s',
            'is_overnight'           => 'nullable|boolean',
            'break_duration_minutes' => 'nullable|integer',
            'expected_hours'         => 'nullable|numeric',
            'grace_minutes'          => 'nullable|integer',
            'weekend'                => 'nullable|string|max:255',
            'is_default'             => 'nullable|boolean',
            'is_active'              => 'nullable|boolean',
        ];
    }
}
