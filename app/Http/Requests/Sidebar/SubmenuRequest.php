<?php

namespace App\Http\Requests\Sidebar;

use Illuminate\Foundation\Http\FormRequest;

class SubmenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $submenuId = $this->route('submenu') ? $this->route('submenu')->id : null;

        return [
            'menu_id' => 'required|exists:menus,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('submenus', 'slug')->ignore($submenuId),
            ],
            'icon' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'permission_id' => 'nullable|exists:permissions,id',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
