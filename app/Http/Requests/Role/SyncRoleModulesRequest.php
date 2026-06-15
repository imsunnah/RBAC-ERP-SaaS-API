<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;

class SyncRoleModulesRequest extends FormRequest
{
    use ResolvesUuidToId;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*' => ['uuid', 'exists:modules,uuid'],
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'modules' => [Module::class],
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
