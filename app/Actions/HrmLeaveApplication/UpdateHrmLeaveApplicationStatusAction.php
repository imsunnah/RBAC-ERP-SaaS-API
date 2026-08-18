<?php

namespace App\Actions\HrmLeaveApplication;

use App\Models\HrmLeaveApplication;
use App\Models\HrmLeaveBalance;
use Illuminate\Support\Facades\Auth;

class UpdateHrmLeaveApplicationStatusAction
{
    /**
     * Execute the action to approve, reject, or cancel a leave application.
     */
    public function execute(HrmLeaveApplication $leave, string $status, ?string $rejectionReason = null): HrmLeaveApplication
    {
        $leave->update([
            'status'           => $status,
            'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
            'updated_by'       => Auth::id(),
        ]);

        if ($status === 'approved') {

            $leaveBalance = HrmLeaveBalance::where('hrm_employee_id', Auth::user()->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->first();

            if ($leaveBalance) {
                $leaveBalance->increment('used_days', $leave->total_days);
            }
        }

        return $leave->load([
            'employee',
            'leaveType',
            'approvedByUser',
            'documents',
        ]);
    }
}
