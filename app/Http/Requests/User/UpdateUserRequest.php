<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Organization;
use App\Models\HrmEmployee;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use ResolvesUuidToId;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof \App\Models\User ? $user->id : $user;

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:45'],
            'employee_id' => ['sometimes', 'nullable', 'uuid', 'exists:hrm_employees,uuid'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'is_admin' => ['sometimes', 'boolean'],
            'organizations' => ['nullable', 'array'],
            'organizations.*' => ['uuid', 'exists:organizations,uuid'],
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'organizations' => [Organization::class],
            'employee_id' => HrmEmployee::class,
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
