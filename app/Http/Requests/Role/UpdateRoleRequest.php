<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    use ResolvesUuidToId;
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
