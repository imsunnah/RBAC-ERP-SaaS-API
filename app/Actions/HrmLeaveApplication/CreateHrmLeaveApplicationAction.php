<?php

namespace App\Actions\HrmLeaveApplication;

use App\Actions\HrmLeaveApplication\Concerns\HandlesLeaveDocuments;
use App\Models\GlobalLookup;
use App\Models\HrmEmployee;
use App\Models\HrmLeaveApplication;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateHrmLeaveApplicationAction
{
    use HandlesLeaveDocuments;

    /**
     * Execute the action to create a leave application.
     */
    public function execute(array $data, ?Request $request = null): HrmLeaveApplication
    {
        return DB::transaction(function () use ($data, $request) {
            $fromDate  = Carbon::parse($data['from_date']);
            $toDate    = Carbon::parse($data['to_date']);
            $totalDays = $fromDate->diffInDays($toDate) + 1;

            $currentOrg = app('current_organization');
            $userId     = Auth::id();

            $leave = HrmLeaveApplication::create([
                'hrm_employee_id' => HrmEmployee::where('uuid', $data['hrm_employee_id'])->value('id'),
                'leave_type_id'   => GlobalLookup::where('uuid', $data['leave_type_id'])->value('id'),
                'from_date'       => $data['from_date'],
                'to_date'         => $data['to_date'],
                'total_days'      => $totalDays,
                'reason'          => $data['reason'] ?? null,
                'status'          => 'pending',
                'organization_id' => $currentOrg?->id,
                'company_id'      => $currentOrg?->company_id,
                'created_by'      => $userId,
                'updated_by'      => $userId,
            ]);

            if ($request) {
                $this->saveUploadedDocuments($request, $leave);
            }

            return $leave->load([
                'employee',
                'leaveType',
                'approvedByUser',
                'documents',
            ]);
        });
    }
}
