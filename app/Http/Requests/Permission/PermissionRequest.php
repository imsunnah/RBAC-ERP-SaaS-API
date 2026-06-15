<?php

namespace App\Http\Requests\Permission;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission') ? $this->route('permission')->id : null;

        return [
            'module_id' => 'required|exists:modules,uuid',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('permissions', 'slug')->ignore($permissionId),
            ],
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:255',
        ];
    }

    /**
     * After validation, resolve UUID to internal integer ID.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'module_id' => Module::resolveId($this->module_id),
        ]);
    }
}
