<?php

namespace App\Actions\HrmLeavePolicy;

use App\Models\HrmLeavePolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateHrmLeavePolicyAction
{
    public function execute(array $validated): HrmLeavePolicy
    {
        $organization = app('current_organization');
        $organizationId = $organization?->id;
        $employeeTypeId = $validated['employee_type_id'] ?? null;

        $duplicate = HrmLeavePolicy::query()
            ->where('organization_id', $organizationId)
            ->where('leave_type_id', $validated['leave_type_id'])
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

        return HrmLeavePolicy::create([
            'company_id' => $organization?->company_id,
            'organization_id' => $organizationId,
            'leave_type_id' => $validated['leave_type_id'],
            'employee_type_id' => $employeeTypeId,
            'leave_balance' => $validated['leave_balance'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ])->load(['leaveType', 'employeeType', 'updater']);
    }
}
