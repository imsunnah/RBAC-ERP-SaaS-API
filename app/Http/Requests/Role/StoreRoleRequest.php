<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = app('current_organization')->id ?? null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 
                'string', 
                'max:100', 
                'alpha_dash',
                \Illuminate\Validation\Rule::unique('roles', 'slug')->where('organization_id', $orgId)
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_role_id' => ['nullable', 'uuid', 'exists:roles,uuid'],
            'is_inheritable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
