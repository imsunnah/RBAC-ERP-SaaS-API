<?php

namespace App\Http\Requests\WorkShift;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                   => 'required|string|max:100',
            'short_code'             => 'nullable|string|max:10',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'required|date_format:H:i',
            'is_overnight'           => 'nullable|boolean',
            'break_duration_minutes' => 'nullable|integer',
            'expected_hours'         => 'nullable|numeric',
            'grace_minutes'          => 'nullable|integer',
            'weekend.*'              => 'required|string|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'is_default'             => 'nullable|boolean',
            'is_active'              => 'nullable|boolean',
        ];
    }
}
