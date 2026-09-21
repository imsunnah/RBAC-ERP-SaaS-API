<?php

namespace App\Actions\HrmLeavePolicy;

use App\Models\HrmLeavePolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UpdateHrmLeavePolicyAction
{
    public function execute(HrmLeavePolicy $policy, array $validated): HrmLeavePolicy
    {
        $leaveTypeId = $validated['leave_type_id'] ?? $policy->leave_type_id;
        $employeeTypeId = array_key_exists('employee_type_id', $validated)
            ? $validated['employee_type_id']
            : $policy->employee_type_id;

        $duplicate = HrmLeavePolicy::query()
            ->where('organization_id', $policy->organization_id)
            ->where('leave_type_id', $leaveTypeId)
            ->where('id', '!=', $policy->id)
            ->where(function ($query) use ($employeeTypeId) {
                $employeeTypeId === null
                    ? $query->whereNull('employee_type_id')
                    : $query->where('employee_type_id', $employeeTypeId);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['A leave policy already exists for this leave type and eligibility.'],
            ]);
        }

        $policy->update([
            'leave_type_id' => $leaveTypeId,
            'employee_type_id' => $employeeTypeId,
            'leave_balance' => $validated['leave_balance'] ?? $policy->leave_balance,
            'updated_by' => Auth::id(),
        ]);

        return $policy->load(['leaveType', 'employeeType', 'updater']);
    }
}
