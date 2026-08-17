<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class SyncUserOrganizationsRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organizations' => ['required', 'array', 'min:1'],
            'organizations.*' => ['uuid', 'exists:organizations,uuid'],
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'organizations' => [Organization::class],
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveUuids();
    }
}
