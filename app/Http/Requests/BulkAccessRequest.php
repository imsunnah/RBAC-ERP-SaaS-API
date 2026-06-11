<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'uuid', 'exists:users,uuid'],
        ];

        if ($this->routeIs('*roles*')) {
            // For role assignment
            $rules['role_id'] = ['required', 'uuid', 'exists:roles,uuid'];
        } else if ($this->routeIs('*permissions*')) {
            // For permission assignment
            $rules['permissions'] = ['required', 'array', 'min:1'];
            $rules['permissions.*.id'] = ['required', 'uuid', 'exists:permissions,uuid'];
            $rules['permissions.*.is_granted'] = ['required', 'boolean'];
        }

        return $rules;
    }
}
