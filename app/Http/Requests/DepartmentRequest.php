<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'organization_id' => 'required|exists:organizations,uuid',
            'company_id' => 'required|exists:companies,uuid',
            'is_active' => 'boolean',
        ];
    }
}
