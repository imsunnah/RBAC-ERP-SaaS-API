<?php

namespace App\Actions\HrmAttendance;

use App\Models\HrmEmployee;
use App\Models\HrmAttendance;
use App\Models\WorkShift;
use Carbon\Carbon;

class DevPushDayAttendanceAction
{
    /**
     * Execute mock day attendance generation.
     */
    public function execute(HrmEmployee $employee, array $data): HrmAttendance
    {
        $dateStr = $data['date'];
        
        $shift = WorkShift::where('organization_id', $employee->organization_id)->first();
        $shiftId = $shift ? $shift->shift_id : 1;

        $checkInDateTime = null;
        $checkOutDateTime = null;
        $workHrs = 0;
        $overtime = 0;
        
        $status = $data['status'] ?? HrmAttendance::STATUS_PRESENT;

        if (!empty($data['check_in'])) {
            $checkInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $data['check_in']);
        }
        
        if (!empty($data['check_out'])) {
            $checkOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $data['check_out']);
        }

        if ($checkInDateTime && $checkOutDateTime) {
            $workHrs = $checkInDateTime->diffInHours($checkOutDateTime) + ($checkInDateTime->diffInMinutes($checkOutDateTime) % 60) / 60;
            $overtime = max(0, $workHrs - 8.0);
        }

        if (empty($data['check_in']) && empty($data['check_out']) && !isset($data['status'])) {
            $status = HrmAttendance::STATUS_ABSENT;
        }

        $attendance = HrmAttendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $dateStr],
            [
                'company_id'      => $employee->company_id,
                'organization_id' => $employee->organization_id,
                'biometric_id'    => $employee->biometric_id,
                'status'          => $status,
                'check_in'        => $checkInDateTime,
                'check_out'       => $checkOutDateTime,
                'working_hours'   => round($workHrs, 2),
                'overtime_hours'  => round($overtime, 2),
                'shift_id'        => $shiftId,
                'source'          => HrmAttendance::SOURCE_WEB,
                'approval_status' => HrmAttendance::APPROVAL_APPROVED,
            ]
        );

        return $attendance;
    }
}
