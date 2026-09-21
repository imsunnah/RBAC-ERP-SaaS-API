<?php

namespace App\Http\Requests\HrmLeaveApplication;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id'     => 'sometimes|required|uuid|exists:hrm_lookups,uuid',
            'from_date'         => 'sometimes|required|date',
            'to_date'           => 'sometimes|required|date|after_or_equal:from_date',
            'start_session'     => 'nullable|in:full_day,first_half,second_half',
            'end_session'       => 'nullable|in:full_day,first_half,second_half',
            'reason'            => 'nullable|string|max:2000',
            'documents'             => 'nullable',
            'documents.*'           => 'nullable',
            'remove_documents'  => 'nullable|array',   // UUIDs of documents to remove
            'remove_documents.*' => 'uuid',
        ];
    }
}
