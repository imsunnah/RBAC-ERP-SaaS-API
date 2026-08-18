<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
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
        $organization = $this->route('organization');
        $orgId = $organization instanceof Organization ? $organization->id : null;

        return [
            'company_id' => 'nullable|exists:companies,uuid',
            'name'       => ['sometimes', 'required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($orgId)],
            'slug'       => ['sometimes', 'required', 'string', 'max:255', Rule::unique('organizations', 'slug')->ignore($orgId)],
            'logo'       => 'nullable|string|max:500',
            'domain'     => ['nullable', 'string', 'max:255', Rule::unique('organizations', 'domain')->ignore($orgId)],
            'timezone'   => 'nullable|string|max:255',
            'currency'   => 'nullable|string|max:10',
            'locale'     => 'nullable|string|max:10',
            'status'     => 'nullable|string|in:active,inactive,suspended',
            'settings'   => 'nullable|array',
        ];
    }
}
