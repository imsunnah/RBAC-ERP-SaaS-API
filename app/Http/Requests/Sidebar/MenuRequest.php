<?php

namespace App\Http\Requests\Sidebar;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
{
    use ResolvesUuidToId;
    public function authorize(): bool
    {
        return $this->user()->is_super_admin;
    }

    public function rules(): array
    {
        $menuId = $this->route('menu') ? $this->route('menu')->id : null;

        return [
            'module_id' => 'required|exists:modules,uuid',
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

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'module_id' => Module::class,
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
