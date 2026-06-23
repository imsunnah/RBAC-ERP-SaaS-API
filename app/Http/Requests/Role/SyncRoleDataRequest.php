<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Menu;
use App\Models\Module;
use App\Models\Submenu;
use Illuminate\Foundation\Http\FormRequest;

class SyncRoleDataRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules'   => 'present|array',
            'modules.*' => 'uuid|exists:modules,uuid',

            'menus'     => 'present|array',
            'menus.*'   => 'uuid|exists:menus,uuid',

            'submenus'     => 'present|array',
            'submenus.*'   => 'uuid|exists:submenus,uuid',

            'permissions'           => 'present|array',
            'permissions.*.id'      => 'required|uuid|exists:permissions,uuid',
            'permissions.*.is_granted' => 'required|boolean',
        ];
    }

    protected function resolvedKeys(): array
    {
        return [
            'modules'  => [Module::class],
            'menus'    => [Menu::class],
            'submenus' => [Submenu::class],
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
