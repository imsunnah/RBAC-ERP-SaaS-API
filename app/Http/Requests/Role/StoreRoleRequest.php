<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    use ResolvesUuidToId;
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

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'parent_role_id' => Role::class,
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
