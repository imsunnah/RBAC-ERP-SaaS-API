<?php

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->is_super_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,uuid',
            'name'       => 'required|string|max:255|unique:organizations,name',
            'slug'       => 'required|string|max:255|unique:organizations,slug',
            'domain'     => 'nullable|string|max:255|unique:organizations,domain',
            'timezone'   => 'nullable|string|max:255',
            'currency'   => 'nullable|string|max:10',
            'locale'     => 'nullable|string|max:10',
            'status'     => 'nullable|string|in:active,inactive,suspended',
            'settings'   => 'nullable|array',
        ];
    }
}
