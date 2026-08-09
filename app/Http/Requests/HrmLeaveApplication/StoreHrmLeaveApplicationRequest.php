<?php

namespace App\Http\Requests\HrmLeaveApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreHrmLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hrm_employee_id' => 'required|uuid|exists:hrm_employees,uuid',
            'leave_type_id'   => 'required|uuid|exists:global_lookups,uuid',
            'from_date'       => 'required|date',
            'to_date'         => 'required|date|after_or_equal:from_date',
            'documents'           => 'nullable|array',
            'documents.*'         => 'nullable',
            'essential_documents'   => 'nullable|array',
            'essential_documents.*' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'to_date.after_or_equal' => 'The to date must be equal to or after the from date.',
        ];
    }
}
