<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class SyncRoleMenusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorize managed via controller middleware (admin.role.update)
    }

    public function rules(): array
    {
        return [
            'menus' => 'present|array',
            'menus.*' => 'exists:menus,id',
            'submenus' => 'present|array',
            'submenus.*' => 'exists:submenus,id',
        ];
    }
}
