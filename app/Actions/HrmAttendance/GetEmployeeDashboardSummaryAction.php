<?php

namespace App\Actions\HrmAttendance;

use App\Models\GlobalLookup;
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
     *
     * @param HrmEmployee $employee
     * @param string|null $targetMonthInput
     * @param array $filters
     * @return array
     */
    public function execute(HrmEmployee $employee, ?string $targetMonthInput = null, array $filters = []): array
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
        $endOfMonth   = $targetMonth->copy()->endOfMonth();
        $totalDaysInMonth = $startOfMonth->daysInMonth;

        // 1. Resolve Date Range for "Hours Logged"
        $days = isset($filters['days']) && is_numeric($filters['days'])
            ? (int) $filters['days']
            : $totalDaysInMonth;

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $hoursLoggedStart = Carbon::parse($filters['from_date'])->startOfDay();
            $hoursLoggedEnd   = Carbon::parse($filters['to_date'])->startOfDay();
        } elseif (!empty($filters['from_date'])) {
            $hoursLoggedStart = Carbon::parse($filters['from_date'])->startOfDay();
            $hoursLoggedEnd   = $hoursLoggedStart->copy()->addDays($days - 1);
        } elseif (!empty($filters['date'])) {
            $refDate          = Carbon::parse($filters['date'])->startOfDay();
            $hoursLoggedStart = $refDate->copy();
            $hoursLoggedEnd   = $hoursLoggedStart->copy()->addDays($days - 1);
        } else {
            // Default: include the complete selected month unless a day limit is supplied.
            $hoursLoggedStart = $startOfMonth->copy()->startOfDay();
            $hoursLoggedEnd   = isset($filters['days']) && is_numeric($filters['days'])
                ? $hoursLoggedStart->copy()->addDays($days - 1)
                : $endOfMonth->copy()->startOfDay();
            if ($hoursLoggedEnd->gt($endOfMonth)) {
                $hoursLoggedEnd = $endOfMonth->copy()->startOfDay();
            }
        }

        // 2. Resolve Office/Today Attendance
        $todayAttendance = HrmAttendance::where('employee_id', $employee->id)
            ->whereDate('date', $today->format('Y-m-d'))
            ->first();

        $shift = GlobalLookup::where('id', $employee->shift_id)->first();
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

        $totalEmployees = HrmEmployee::where('organization_id', $employee->organization_id)
            ->where('is_active', true)
            ->count();

        $presentEmployees = HrmAttendance::where('organization_id', $employee->organization_id)
            ->whereDate('date', $today->toDateString())
            ->whereIn('status', [HrmAttendance::STATUS_PRESENT, HrmAttendance::STATUS_LATE])
            ->whereHas('employee', fn ($query) => $query->where('is_active', true))
            ->distinct('employee_id')
            ->count('employee_id');

        $leaveEmployees = HrmLeaveApplication::where('organization_id', $employee->organization_id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $today->toDateString())
            ->whereDate('to_date', '>=', $today->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('is_active', true))
            ->distinct('hrm_employee_id')
            ->count('hrm_employee_id');

        $leaveAndAbsenceEmployees = max(0, $totalEmployees - $presentEmployees);
        $attendanceTotal = $totalEmployees ?: 1;

        $employeeAttendance = [
            'date' => $today->toDateString(),
            'total_employees' => $totalEmployees,
            'present' => [
                'count' => $presentEmployees,
                'percentage' => round(($presentEmployees / $attendanceTotal) * 100),
            ],
            'leave_and_absence' => [
                'count' => $leaveAndAbsenceEmployees,
                'percentage' => round(($leaveAndAbsenceEmployees / $attendanceTotal) * 100),
                'leave_count' => min($leaveEmployees, $leaveAndAbsenceEmployees),
                'absence_count' => max(0, $leaveAndAbsenceEmployees - $leaveEmployees),
            ],
        ];

        // 3. Fetch data spanning both Month Overview and Hours Logged date ranges
        $queryStartDate = $startOfMonth->min($hoursLoggedStart)->format('Y-m-d');
        $queryEndDate   = $endOfMonth->max($hoursLoggedEnd)->format('Y-m-d');

        $attendances = HrmAttendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$queryStartDate, $queryEndDate])
            ->get()
            ->keyBy(fn($a) => Carbon::parse($a->date)->format('Y-m-d'));

        $leaves = HrmLeaveApplication::where('hrm_employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($queryStartDate, $queryEndDate) {
                $q->whereBetween('from_date', [$queryStartDate, $queryEndDate])
                  ->orWhereBetween('to_date', [$queryStartDate, $queryEndDate])
                  ->orWhere(function ($sub) use ($queryStartDate, $queryEndDate) {
                      $sub->where('from_date', '<=', $queryStartDate)
                          ->where('to_date', '>=', $queryEndDate);
                  });
            })
            ->get();

        $holidays = HrmHoliday::where('organization_id', $employee->organization_id)
            ->where('is_active', 1)
            ->whereBetween('date', [$queryStartDate, $queryEndDate])
            ->get();

        // 4. Calculate Monthly Overview
        $presentCount = 0;
        $absentCount = 0;
        $leaveCount = 0;
        $holidayCount = 0;
        $weeklyHolidayCount = 0;

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
                return $holiday->date && $holiday->date->format('Y-m-d') === $dateStr;
            });

            $att = $attendances->get($dateStr);

            if ($att) {
                if (in_array(strtoupper($att->status), ['PRESENT', 'LATE'])) {
                    $presentCount++;
                } else {
                    $absentCount++;
                }
            } elseif ($hasLeave) {
                $leaveCount++;
            } elseif ($hasHoliday) {
                $holidayCount++;
            } elseif ($isWeekend) {
                $weeklyHolidayCount++;
            } else {
                if ($currentDate->lt($today->copy()->startOfDay())) {
                    $absentCount++;
                }
            }
        }

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

        // 5. Compile "Hours Logged" for the resolved date range (full month)
        $hoursLoggedList = [];
        $periodDays = $hoursLoggedStart->diffInDays($hoursLoggedEnd) + 1;

        for ($i = 0; $i < $periodDays; $i++) {
            $currentDate = $hoursLoggedStart->copy()->addDays($i);
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek; // 0=Sunday, 5=Friday, 6=Saturday
            
            $isWeekend = ($dayOfWeek === 5 || $dayOfWeek === 6);

            // Resolve leave
            $hasLeave = $leaves->first(function ($leave) use ($dateStr) {
                return $dateStr >= $leave->from_date && $dateStr <= $leave->to_date;
            });

            // Resolve holiday
            $hasHoliday = $holidays->first(function ($holiday) use ($dateStr) {
                return $holiday->date && $holiday->date->format('Y-m-d') === $dateStr;
            });

            $att = $attendances->get($dateStr);
            
            $status = 'Absent';
            $hours = '00:00';
            $color = 'orange';

            if ($att) {
                $attendanceStatus = strtoupper(str_replace(['-', ' '], '_', (string) $att->status));

                if (in_array($attendanceStatus, ['PRESENT', 'LATE'])) {
                    $isLate = $attendanceStatus === 'LATE';
                    $status = $isLate ? 'Late' : 'Present';
                    $hours = $att->working_hours ? sprintf('%02d:%02d', floor($att->working_hours), round(($att->working_hours - floor($att->working_hours)) * 60)) : '00:00';
                    $color = $isLate ? 'red' : 'blue';
                } elseif ($attendanceStatus === 'HALF_DAY') {
                    $status = 'Half day';
                    $hours = $att->working_hours ? sprintf('%02d:%02d', floor($att->working_hours), round(($att->working_hours - floor($att->working_hours)) * 60)) : '00:00';
                    $color = 'yellow';
                } else {
                    $status = 'Absent';
                }
            } elseif ($hasLeave) {
                $status = 'Leave';
                $color = 'purple';
            } elseif ($hasHoliday) {
                $status = 'Holiday';
                $color = 'orange';
            } elseif ($isWeekend) {
                $status = 'Weekly holiday';
                $color = 'green';
                $hours = '00:00';
            } else {
                if ($currentDate->lt($today->copy()->startOfDay())) {
                    $status = 'Absent';
                } else {
                    $status = 'Future';
                    $color = 'gray';
                }
            }

            $hoursLoggedList[] = [
                'date'     => $dateStr,
                'day'      => sprintf('%02d', $currentDate->day),
                'day_name' => $currentDate->format('D'),
                'hours'    => $hours,
                'status'   => $status,
                'color'    => $color,
            ];
        }

        return [
            'office_attendance' => $todaySection,
            'employee_attendance' => $employeeAttendance,
            'overview'          => $overview,
            'hours_logged'      => $hoursLoggedList,
        ];
    }
}
