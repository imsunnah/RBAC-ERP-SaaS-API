<?php

namespace App\Actions\HrmLeaveApplication;

use App\Models\HrmLeaveApplication;
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

        return $leave->load([
            'employee',
            'leaveType',
            'approvedByUser',
            'documents',
        ]);
    }
}
