<?php

namespace App\Http\Requests\Module;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class ModuleUpdateRequest extends FormRequest
{
    use ResolvesUuidToId;

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
            'organizations' => 'nullable|array',
            'organizations.*' => 'uuid|exists:organizations,uuid',
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'organizations' => [Organization::class],
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
