<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = app('current_organization');

        return [
            'team_name' => ['required', 'string', 'max:255'],
            'hrm_employee_id' => [
                'required',
                Rule::exists('hrm_employees', 'uuid')->where('organization_id', $organization->id),
            ],
            'active_status' => ['sometimes', 'boolean'],
            'department'    => ['nullable', 'array'],
            'department.*.id'    => ['nullable', 'string'],
            'department.*.uuid'    => ['nullable', 'string'],
            'department.*.department_id'    => ['nullable', 'string'],
            'department.*.active_status'    => ['nullable', 'boolean']
        ];
    }
}
