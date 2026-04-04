<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_role_id' => ['nullable', 'uuid', 'exists:roles,uuid'],
            'is_inheritable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
