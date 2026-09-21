<?php

namespace App\Actions\HrmLeaveApplication;

use App\Actions\HrmLeaveApplication\Concerns\HandlesLeaveDocuments;
use App\Models\HrmEmployee;
use App\Models\HrmLeaveApplication;
use App\Models\HrmLeaveBalance;
use App\Models\HrmLookup;
use App\Services\HrmLeave\LeaveCalculationService;
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
    public function execute(array $data, ?Request $request = null, ?LeaveCalculationService $calculator = null): HrmLeaveApplication
    {
        return DB::transaction(function () use ($data, $request, $calculator) {
            $calculator ??= app(LeaveCalculationService::class);
            $fromDate  = Carbon::parse($data['from_date']);
            $toDate    = Carbon::parse($data['to_date']);

            $currentOrg = app('current_organization');
            $userId     = Auth::id();

            $employeeId = null;
            if (!empty($data['hrm_employee_id'])) {
                $employeeId = HrmEmployee::where('uuid', $data['hrm_employee_id'])->value('id');
            } else {
                $user = Auth::user();
                if ($user) {
                    $employeeId = $user->employee_id ?: $user->id;
                }
            }

            $leave = HrmLeaveApplication::create([
                'hrm_employee_id' => $employeeId,
                'leave_type_id'   => HrmLookup::where('uuid', $data['leave_type_id'])->value('id'),
                'from_date'       => $data['from_date'],
                'to_date'         => $data['to_date'],
                'start_session'   => $data['start_session'] ?? 'full_day',
                'end_session'     => $data['end_session'] ?? 'full_day',
                'total_days'      => $calculator->calculateDays(
                    $fromDate,
                    $toDate,
                    $data['start_session'] ?? 'full_day',
                    $data['end_session'] ?? 'full_day'
                ),
                'reason'          => $data['reason'] ?? null,
                'status'          => 'pending',
                'organization_id' => $currentOrg?->id,
                'company_id'      => $currentOrg?->company_id,
                'created_by'      => $userId,
                'updated_by'      => $userId,
            ]);

            $calculator->validateApplication($leave, (float) $leave->total_days);
            HrmLeaveBalance::where('hrm_employee_id', $leave->hrm_employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', $fromDate->year)
                ->increment('pending_days', $leave->total_days);

            $this->saveUploadedDocuments($request, $leave, $data);

            return $leave->load([
                'employee',
                'leaveType',
                'approvedByUser',
                'documents',
            ]);
        });
    }
}
