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
            'module_id'   => 'required|exists:modules,uuid',
            'name'        => 'required|string|max:255',
            'slug'        => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('permissions', 'slug')->ignore($permissionId),
            ],
            'description' => 'nullable|string',
            'group'       => 'nullable|string|max:255',
        ];
    }

    /**
     * Resolve module UUID to internal integer ID when retrieving validated data.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key === 'module_id' && is_string($validated) && preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $validated)) {
            return Module::resolveId($validated);
        }

        if (is_array($validated) && isset($validated['module_id']) && is_string($validated['module_id']) && preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $validated['module_id'])) {
            $validated['module_id'] = Module::resolveId($validated['module_id']);
        }

        return $validated;
    }
}
