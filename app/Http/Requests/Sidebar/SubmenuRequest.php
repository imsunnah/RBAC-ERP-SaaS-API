<?php

namespace App\Http\Requests\Sidebar;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SubmenuRequest extends FormRequest
{
    use ResolvesUuidToId;
    public function authorize(): bool
    {
        return $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $submenuId = $this->route('submenu') ? $this->route('submenu')->id : null;

        return [
            'menu_id' => 'required|exists:menus,uuid',
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
            'permission_id' => 'nullable|exists:permissions,uuid',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'menu_id' => Menu::class,
            'permission_id' => Permission::class,
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
