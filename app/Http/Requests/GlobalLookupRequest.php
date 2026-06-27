<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\GlobalLookupGroup;
use Illuminate\Foundation\Http\FormRequest;

class GlobalLookupRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'global_lookup_group_id' => 'nullable|uuid|exists:global_lookup_groups,uuid',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    protected function resolvedKeys(): array
    {
        return [
            'global_lookup_group_id' => GlobalLookupGroup::class,
        ];
    }
}
