<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Organization;
use App\Models\HrmEmployee;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use ResolvesUuidToId;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'first_name' => ['nullable', 'string', 'max:255'],
            // 'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // 'phone' => ['nullable', 'string', 'max:45'],
            'employee_id' => ['required', 'uuid', 'exists:hrm_employees,uuid'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'is_admin' => ['sometimes', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['uuid', 'exists:roles,uuid'],
            'organization_id' => ['required', 'array'],
            'organization_id.*' => ['required', 'uuid', 'exists:organizations,uuid'],
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'roles' => [Role::class],
            'organization_id' => [Organization::class],
            'employee_id' => HrmEmployee::class,
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
