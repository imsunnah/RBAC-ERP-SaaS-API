<?php

namespace App\Http\Requests\HrmLeaveBalance;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\HrmEmployee;
use App\Models\HrmLookup;

class StoreHrmLeaveBalanceRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    protected function resolvedKeys(): array
    {
        return [
            'hrm_employee_id' => HrmEmployee::class,
            'balances.*.leave_type_id' => HrmLookup::class,
            'leave_type_id' => HrmLookup::class,
        ];
    }

    public function rules(): array
    {
        if ($this->has('balances') && is_array($this->input('balances'))) {
            return [
                'hrm_employee_id'          => 'required|uuid|exists:hrm_employees,uuid',
                'year'                     => 'required|integer|min:2000|max:2100',
                'balances'                 => 'required|array|min:1',
                'balances.*.leave_type_id' => 'required|uuid|exists:hrm_lookups,uuid',
                'balances.*.total_days'    => 'required|numeric|min:0|max:366',
            ];
        }

        return [
            'hrm_employee_id' => 'required|uuid|exists:hrm_employees,uuid',
            'leave_type_id'   => 'required|uuid|exists:hrm_lookups,uuid',
            'year'            => 'required|integer|min:2000|max:2100',
            'total_days'      => 'required|numeric|min:0|max:366',
        ];
    }
}
