<?php

namespace App\Actions\HrmAttendance;

use App\Models\HrmEmployee;
use App\Models\HrmAttendance;
use App\Models\HrmLeaveApplication;
use App\Models\HrmHoliday;
use App\Models\WorkShift;
use Carbon\Carbon;

class GetEmployeeDashboardSummaryAction
{
    /**
     * Execute the summary retrieval for the employee.
     */
    public function execute(HrmEmployee $employee, ?string $targetMonthInput = null): array
    {
        $today = Carbon::now();
        
        if ($targetMonthInput === '0' || $targetMonthInput === 0 || empty($targetMonthInput)) {
            $targetMonth = $today->copy();
        } else {
            // Check if it's MM-YYYY format and convert to YYYY-MM
            if (preg_match('/^\d{2}-\d{4}$/', $targetMonthInput)) {
                [$m, $y] = explode('-', $targetMonthInput);
                $targetMonthInput = "{$y}-{$m}";
            }
            try {
                $targetMonth = Carbon::parse($targetMonthInput . '-01');
            } catch (\Exception $e) {
                $targetMonth = $today->copy();
            }
        }
            
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        // 1. Resolve Office/Today Attendance
        $todayAttendance = HrmAttendance::where('employee_id', $employee->id)
            ->whereDate('date', $today->format('Y-m-d'))
            ->first();

        $shift = $employee->workShift ?? WorkShift::where('organization_id', $employee->organization_id)->first();
        $shiftStart = $shift ? Carbon::parse($shift->start_time)->format('g:i A') : '10:00 AM';
        $shiftEnd = $shift ? Carbon::parse($shift->end_time)->format('g:i A') : '7:00 PM';
        $officeTimeStr = "{$shiftStart}-{$shiftEnd}";

        $todayLoggedSecs = 0;
        $checkInTimeStr = null;
        $checkOutTimeStr = null;

        if ($todayAttendance) {
            if ($todayAttendance->check_in) {
                $checkInTimeStr = Carbon::parse($todayAttendance->check_in)->format('g:i A');
                if ($todayAttendance->check_out) {
                    $checkOutTimeStr = Carbon::parse($todayAttendance->check_out)->format('g:i A');
                    $todayLoggedSecs = Carbon::parse($todayAttendance->check_in)->diffInSeconds(Carbon::parse($todayAttendance->check_out));
                } else {
                    $todayLoggedSecs = Carbon::parse($todayAttendance->check_in)->diffInSeconds(Carbon::now());
                }
            }
        }

        $formattedLoggedTime = sprintf('%02d:%02d:%02d', 
            floor($todayLoggedSecs / 3600), 
            floor(($todayLoggedSecs / 60) % 60), 
            $todayLoggedSecs % 60
        );

        $targetHoursSecs = 8 * 3600; // 8 hours
        $progressPercent = min(100, round(($todayLoggedSecs / $targetHoursSecs) * 100, 2));

        $todaySection = [
            'date_formatted'   => $today->format('d F Y | l'),
            'check_in'         => $checkInTimeStr,
            'check_out'        => $checkOutTimeStr,
            'logged_hours'     => $formattedLoggedTime,
            'target_hours'     => '08:00:00',
            'progress_percent' => $progressPercent,
            'office_time'      => $officeTimeStr,
        ];

        // 2. Fetch monthly data (Attendances, Leaves, Holidays)
        $attendances = HrmAttendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy(fn($a) => Carbon::parse($a->date)->format('Y-m-d'));

        $leaves = HrmLeaveApplication::where('hrm_employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('from_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                  ->orWhereBetween('to_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                  ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                      $sub->where('from_date', '<=', $startOfMonth->format('Y-m-d'))
                          ->where('to_date', '>=', $endOfMonth->format('Y-m-d'));
                  });
            })
            ->get();

        $holidays = HrmHoliday::where('organization_id', $employee->organization_id)
            ->where('is_active', 1)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get();

        // 3. Loop through days of target month and compile calendar/stats
        $totalDaysInMonth = $startOfMonth->daysInMonth;
        $presentCount = 0;
        $absentCount = 0;
        $leaveCount = 0;
        $holidayCount = 0;
        $weeklyHolidayCount = 0;
        
        $hoursLoggedList = [];

        for ($day = 1; $day <= $totalDaysInMonth; $day++) {
            $currentDate = $startOfMonth->copy()->day($day);
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek; // 0=Sunday, 5=Friday, 6=Saturday
            
            $isWeekend = ($dayOfWeek === 5 || $dayOfWeek === 6); // Friday, Saturday

            // Resolve leave
            $hasLeave = $leaves->first(function ($leave) use ($dateStr) {
                return $dateStr >= $leave->from_date && $dateStr <= $leave->to_date;
            });

            // Resolve holiday
            $hasHoliday = $holidays->first(function ($holiday) use ($dateStr) {
                return $holiday->date === $dateStr;
            });

            $att = $attendances->get($dateStr);
            
            $status = 'Absent';
            $hours = '00:00';
            $color = 'orange';

            if ($att) {
                if (in_array(strtoupper($att->status), ['PRESENT', 'LATE'])) {
                    $isLate = strtoupper($att->status) === 'LATE';
                    $status = $isLate ? 'Late' : 'Present';
                    $hours = $att->working_hours ? sprintf('%02d:%02d', floor($att->working_hours), round(($att->working_hours - floor($att->working_hours)) * 60)) : '00:00';
                    $color = $isLate ? 'red' : 'blue';
                    $presentCount++;
                } else {
                    $status = 'Absent';
                    $absentCount++;
                }
            } elseif ($hasLeave) {
                $status = 'Leave';
                $color = 'purple';
                $leaveCount++;
            } elseif ($hasHoliday) {
                $status = 'Holiday';
                $color = 'orange';
                $holidayCount++;
            } elseif ($isWeekend) {
                $status = 'Weekly holiday';
                $color = 'green';
                $hours = '08:45';
                $weeklyHolidayCount++;
            } else {
                // If in the past, it's considered absent
                if ($currentDate->lt($today->startOfDay())) {
                    $status = 'Absent';
                    $absentCount++;
                } else {
                    $status = 'Future';
                    $color = 'gray';
                }
            }

            $hoursLoggedList[] = [
                'day'    => sprintf('%02d', $day),
                'hours'  => $hours,
                'status' => $status,
                'color'  => $color,
            ];
        }

        // Calculate Overview
        $totalAccountedDays = $presentCount + $absentCount + $leaveCount + $holidayCount + $weeklyHolidayCount;
        $totalAccountedDays = $totalAccountedDays ?: 1;

        $overview = [
            'month_label' => $targetMonth->format('F'),
            'month_year'  => $targetMonth->format('Y'),
            'total_days'  => $totalDaysInMonth,
            'attendance_ratio' => "{$presentCount}/{$totalDaysInMonth}",
            'stats' => [
                'present' => [
                    'label' => 'Present',
                    'days'  => sprintf('%02d Days', $presentCount),
                    'percentage' => round(($presentCount / $totalAccountedDays) * 100) . '%'
                ],
                'absent' => [
                    'label' => 'Absent',
                    'days'  => sprintf('%02d Days', $absentCount),
                    'percentage' => round(($absentCount / $totalAccountedDays) * 100) . '%'
                ],
                'leave' => [
                    'label' => 'Leave',
                    'days'  => sprintf('%02d Days', $leaveCount),
                    'percentage' => round(($leaveCount / $totalAccountedDays) * 100) . '%'
                ],
                'holiday' => [
                    'label' => 'Holiday',
                    'days'  => sprintf('%02d Days', $holidayCount + $weeklyHolidayCount),
                    'percentage' => round((($holidayCount + $weeklyHolidayCount) / $totalAccountedDays) * 100) . '%'
                ]
            ]
        ];

        return [
            'office_attendance' => $todaySection,
            'overview'          => $overview,
            'hours_logged'      => $hoursLoggedList,
        ];
    }
}
