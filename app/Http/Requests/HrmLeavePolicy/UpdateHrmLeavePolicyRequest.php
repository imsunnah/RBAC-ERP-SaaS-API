<?php

namespace App\Http\Requests\HrmLeavePolicy;

use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\HrmLookup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmLeavePolicyRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    protected function resolvedKeys(): array
    {
        return [
            'leave_type_id' => HrmLookup::class,
            'employee_type_id' => HrmLookup::class,
        ];
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['sometimes', 'required', 'uuid', 'exists:hrm_lookups,uuid'],
            'employee_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:hrm_lookups,uuid'],
            'leave_balance' => ['sometimes', 'required', 'numeric', 'min:0', 'max:366'],
        ];
    }
}
