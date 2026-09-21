<?php

namespace App\Actions\HrmLeaveApplication;

use App\Models\HrmLeaveApplication;
use App\Models\HrmLeaveBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateHrmLeaveApplicationStatusAction
{
    /**
     * Execute the action to approve, reject, or cancel a leave application.
     */
    public function execute(HrmLeaveApplication $leave, string $status, ?string $rejectionReason = null): HrmLeaveApplication
    {
        return DB::transaction(function () use ($leave, $status, $rejectionReason) {
            if ($leave->status === $status) {
                return $leave->load(['employee', 'leaveType', 'approvedByUser', 'documents']);
            }

            $balance = HrmLeaveBalance::where('hrm_employee_id', $leave->hrm_employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', $leave->from_date->year)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leave->status === 'pending') {
                $reserved = min((float) ($balance->pending_days ?? 0), (float) $leave->total_days);
                if ($reserved > 0) {
                    $balance->decrement('pending_days', $reserved);
                }
            } elseif ($leave->status === 'approved') {
                $balance->decrement('used_days', $leave->total_days);
            }

            if ($status === 'approved') {
                $balance->increment('used_days', $leave->total_days);
            } elseif ($status === 'pending') {
                $balance->increment('pending_days', $leave->total_days);
            }

            $leave->update([
                'status'           => $status,
                'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
                'approved_by'      => Auth::id(),
                'approved_at'      => $status === 'approved' ? now() : $leave->approved_at,
                'cancelled_at'     => $status === 'cancelled' ? now() : null,
                'updated_by'       => Auth::id(),
            ]);

            return $leave->load(['employee', 'leaveType', 'approvedByUser', 'documents']);
        });
    }
}
