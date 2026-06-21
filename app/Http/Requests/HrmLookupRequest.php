<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\HrmLookupGroup;
use Illuminate\Foundation\Http\FormRequest;

class HrmLookupRequest extends FormRequest
{
    use ResolvesUuidToId;
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hrm_lookup_group_id' => 'nullable|uuid|exists:hrm_lookup_groups,uuid',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

     protected function resolvedKeys(): array
    {
        return [
            'hrm_lookup_group_id'     => HrmLookupGroup::class,
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If it's a UUID, it might need resolution, 
        // but for now we expect the internal ID or a trait will handle it.
    }
}
