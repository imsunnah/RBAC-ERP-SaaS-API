<?php

namespace App\Http\Requests\Sidebar;

use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $menuId = $this->route('menu') ? $this->route('menu')->id : null;

        return [
            'module_id' => 'required|exists:modules,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('menus', 'slug')->ignore($menuId),
            ],
            'icon' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
