<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Menu;
use App\Models\Submenu;
use Illuminate\Foundation\Http\FormRequest;

class SyncRoleMenusRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true; // Authorize managed via controller middleware (admin.role.update)
    }

    public function rules(): array
    {
        return [
            'menus' => 'present|array',
            'menus.*' => 'exists:menus,uuid',
            'submenus' => 'present|array',
            'submenus.*' => 'exists:submenus,uuid',
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'menus' => [Menu::class],
            'submenus' => [Submenu::class],
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
