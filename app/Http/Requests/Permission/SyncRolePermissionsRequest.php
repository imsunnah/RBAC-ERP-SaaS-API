<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*.id' => ['required', 'uuid', 'exists:permissions,uuid'],
            'permissions.*.is_granted' => ['required', 'boolean'],
        ];
    }
}
