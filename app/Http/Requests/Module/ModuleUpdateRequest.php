<?php

namespace App\Http\Requests\Module;

use Illuminate\Foundation\Http\FormRequest;

class ModuleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $moduleId = $this->route('module') ? $this->route('module')->id : null;

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('modules', 'slug')->ignore($moduleId),
            ],
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_core' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'url' => 'sometimes|string|max:255',
        ];
    }
}
