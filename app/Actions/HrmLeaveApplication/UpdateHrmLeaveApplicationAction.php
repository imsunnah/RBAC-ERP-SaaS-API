<?php

namespace App\Actions\HrmLeaveApplication;

use App\Actions\HrmLeaveApplication\Concerns\HandlesLeaveDocuments;
use App\Models\HrmLeaveApplication;
use App\Models\HrmLookup;
use App\Models\HrmLeaveBalance;
use App\Services\HrmLeave\LeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateHrmLeaveApplicationAction
{
    use HandlesLeaveDocuments;

    /**
     * Execute the action to update a leave application.
     */
    public function execute(
        HrmLeaveApplication $leave,
        array $validated,
        ?Request $request = null
    ): HrmLeaveApplication {
        return DB::transaction(function () use ($leave, $validated, $request) {
            $calculator = app(LeaveCalculationService::class);
            $updateData = [];
            $oldLeaveTypeId = $leave->leave_type_id;
            $oldYear = $leave->from_date->year;
            $oldTotalDays = (float) $leave->total_days;

            if (isset($validated['leave_type_id'])) {
                $updateData['leave_type_id'] = HrmLookup::where('uuid', $validated['leave_type_id'])->value('id');
            }
            if (isset($validated['from_date'])) {
                $updateData['from_date'] = $validated['from_date'];
            }
            if (isset($validated['to_date'])) {
                $updateData['to_date'] = $validated['to_date'];
            }
            if (isset($validated['start_session'])) {
                $updateData['start_session'] = $validated['start_session'];
            }
            if (isset($validated['end_session'])) {
                $updateData['end_session'] = $validated['end_session'];
            }
            if (array_key_exists('reason', $validated)) {
                $updateData['reason'] = $validated['reason'];
            }
            if (isset($validated['rejection_reason'])) {
                $updateData['rejection_reason'] = $validated['rejection_reason'];
            }

            if ($leave->status !== 'pending') {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Only pending leave applications can be modified.',
                ], 422));
            }

            $fromDate = Carbon::parse($updateData['from_date'] ?? $leave->from_date);
            $toDate   = Carbon::parse($updateData['to_date'] ?? $leave->to_date);
            $startSession = $updateData['start_session'] ?? $leave->start_session;
            $endSession = $updateData['end_session'] ?? $leave->end_session;
            $updateData['total_days'] = $calculator->calculateDays($fromDate, $toDate, $startSession, $endSession);

            $oldBalance = HrmLeaveBalance::where('hrm_employee_id', $leave->hrm_employee_id)
                ->where('leave_type_id', $oldLeaveTypeId)
                ->where('year', $oldYear)
                ->lockForUpdate()
                ->firstOrFail();
            $oldBalance->decrement('pending_days', $oldTotalDays);

            $updateData['updated_by'] = Auth::id();
            $leave->update($updateData);
            $calculator->validateApplication($leave, (float) $leave->total_days);
            HrmLeaveBalance::where('hrm_employee_id', $leave->hrm_employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', $fromDate->year)
                ->increment('pending_days', $leave->total_days);

            // Remove specified documents
            if (!empty($validated['remove_documents'])) {
                $this->removeDocuments($leave, $validated['remove_documents']);
            }

            // Add new uploaded documents
            $this->saveUploadedDocuments($request, $leave, $validated);

            return $leave->load([
                'employee',
                'leaveType',
                'approvedByUser',
                'documents',
            ]);
        });
    }
}
