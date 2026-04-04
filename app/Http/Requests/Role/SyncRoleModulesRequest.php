<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class SyncRoleModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*' => ['uuid', 'exists:modules,uuid'],
        ];
    }
}
