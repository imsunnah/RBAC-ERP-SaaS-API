<?php

namespace App\Actions\HrmLeaveApplication;

use App\Actions\HrmLeaveApplication\Concerns\HandlesLeaveDocuments;
use App\Models\HrmLeaveApplication;
use App\Models\HrmLookup;
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
            $updateData = [];

            if (isset($validated['leave_type_id'])) {
                $updateData['leave_type_id'] = HrmLookup::where('uuid', $validated['leave_type_id'])->value('id');
            }
            if (isset($validated['from_date'])) {
                $updateData['from_date'] = $validated['from_date'];
            }
            if (isset($validated['to_date'])) {
                $updateData['to_date'] = $validated['to_date'];
            }
            if (array_key_exists('reason', $validated)) {
                $updateData['reason'] = $validated['reason'];
            }
            if (isset($validated['rejection_reason'])) {
                $updateData['rejection_reason'] = $validated['rejection_reason'];
            }

            // Handle status change
            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];

                if (in_array($validated['status'], ['approved', 'rejected'], true)) {
                    $updateData['approved_by'] = Auth::id();
                    $updateData['approved_at'] = now();
                }
            }

            // Recalculate total_days if dates changed
            $fromDate = Carbon::parse($updateData['from_date'] ?? $leave->from_date);
            $toDate   = Carbon::parse($updateData['to_date'] ?? $leave->to_date);
            $updateData['total_days'] = $fromDate->diffInDays($toDate) + 1;

            $updateData['updated_by'] = Auth::id();
            $leave->update($updateData);

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
