<?php

namespace App\Actions\HrmAttendance;

use App\Models\HrmAttendance;
use App\Models\HrmEmployee;
use App\Models\HrmHoliday;
use App\Models\HrmLeaveApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAttendanceAndLeaveSummaryAction
{
    /**
     * Execute the Attendance & Leave daily summary calculation.
     *
     * @param string|null $dateInput Optional date string (YYYY-MM-DD, d-m-Y, etc.)
     * @return array
     */
    public function execute(?string $dateInput = null): array
    {
        $today = Carbon::today();

        // 1. Resolve Target Date (defaults to today if empty/invalid)
        if (!empty($dateInput)) {
            try {
                $targetDate = Carbon::parse($dateInput)->startOfDay();
            } catch (\Throwable $e) {
                $targetDate = $today->copy();
            }
        } else {
            $targetDate = $today->copy();
        }

        $dateStr        = $targetDate->toDateString();
        $isToday        = $targetDate->isToday();
        $dayOfWeek      = $targetDate->dayOfWeek; // 5 = Friday, 6 = Saturday (or weekend)
        $isWeekend      = ($dayOfWeek === 5 || $dayOfWeek === 6);

        // Check if holiday
        $holiday = HrmHoliday::where('is_active', 1)
            ->whereDate('date', $dateStr)
            ->first();
        $isHoliday   = !empty($holiday);
        $holidayName = $isHoliday ? $holiday->name : null;

        // 2. Total Active Employees
        $totalEmployees = HrmEmployee::where('is_active', true)->count();

        // 3. Month-Over-Month Growth Calculation
        $endOfLastMonth     = Carbon::now()->subMonth()->endOfMonth();
        $employeesLastMonth = HrmEmployee::where('is_active', true)
            ->where('created_at', '<=', $endOfLastMonth)
            ->count();

        if ($employeesLastMonth > 0) {
            $growthDiff = $totalEmployees - $employeesLastMonth;
            $growthRate = round(($growthDiff / $employeesLastMonth) * 100, 1);
            $growthSign = $growthRate >= 0 ? '+' : '';
            $growthText = "{$growthSign}{$growthRate}% from last month";
        } else {
            $growthRate = $totalEmployees > 0 ? 100.0 : 0.0;
            $growthText = $totalEmployees > 0 ? "+100% from last month" : "0% from last month";
        }

        // 4. Present Count on Target Date (Status in PRESENT or LATE)
        $presentCount = HrmAttendance::whereDate('date', $dateStr)
            ->whereIn(DB::raw('UPPER(status)'), [HrmAttendance::STATUS_PRESENT, HrmAttendance::STATUS_LATE])
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->distinct('employee_id')
            ->count('employee_id');

        $lateCount = HrmAttendance::whereDate('date', $dateStr)
            ->where(DB::raw('UPPER(status)'), HrmAttendance::STATUS_LATE)
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->distinct('employee_id')
            ->count('employee_id');

        $onTimeCount = max(0, $presentCount - $lateCount);

        // 5. Approved Leave Count on Target Date
        $leaveCount = HrmLeaveApplication::where(function ($q) {
                $q->where(DB::raw('UPPER(status)'), 'APPROVED');
            })
            ->whereDate('from_date', '<=', $dateStr)
            ->whereDate('to_date', '>=', $dateStr)
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->distinct('hrm_employee_id')
            ->count('hrm_employee_id');

        // 6. Leave & Absence and Absent Count
        $leaveAndAbsenceCount = max(0, $totalEmployees - $presentCount);
        $absentCount          = max(0, $leaveAndAbsenceCount - $leaveCount);

        // 7. Calculate Percentages (avoid divide by zero)
        $baseTotal = $totalEmployees > 0 ? $totalEmployees : 1;

        $presentPct         = round(($presentCount / $baseTotal) * 100);
        $leaveAndAbsencePct = round(($leaveAndAbsenceCount / $baseTotal) * 100);
        $leavePct           = round(($leaveCount / $baseTotal) * 100);
        $absentPct          = round(($absentCount / $baseTotal) * 100);

        // Accurate decimal percentages for charts / badges
        $presentPctDec         = round(($presentCount / $baseTotal) * 100, 1);
        $leaveAndAbsencePctDec = round(($leaveAndAbsenceCount / $baseTotal) * 100, 1);
        $leavePctDec           = round(($leaveCount / $baseTotal) * 100, 1);
        $absentPctDec          = round(($absentCount / $baseTotal) * 100, 1);

        $attendanceTitle = $isToday
            ? 'Today Employee Attendance'
            : 'Employee Attendance for ' . $targetDate->format('d M, Y');

        $filterLabel = $isToday ? 'Today' : $targetDate->format('d M, Y');

        return [
            'date'           => $dateStr,
            'formatted_date' => $targetDate->format('d F Y | l'),
            'filter_label'   => $filterLabel,
            'is_today'       => $isToday,
            'is_weekend'     => $isWeekend,
            'is_holiday'     => $isHoliday,
            'holiday_name'   => $holidayName,

            // ── Card 1: Total Employee Widget (Left Card) ───────────────────────
            'total_employee_card' => [
                'card_title'        => 'Total Employee',
                'total_employees'   => $totalEmployees,
                'growth_rate'       => $growthRate,
                'growth_percentage' => ($growthRate >= 0 ? '+' : '') . "{$growthRate}%",
                'growth_badge'      => $growthText,
                'period_label'      => 'from last month',
                'is_positive'       => $growthRate >= 0,
                'attendance_bar'    => [
                    'section_title'     => $attendanceTitle,
                    'present'           => [
                        'label'            => 'Present',
                        'count'            => $presentCount,
                        'percentage'       => "{$presentPct}%",
                        'percentage_value' => $presentPct,
                        'color'            => '#2563EB', // Blue
                    ],
                    'leave_and_absence' => [
                        'label'            => 'Leave & Absence',
                        'count'            => $leaveAndAbsenceCount,
                        'percentage'       => "{$leaveAndAbsencePct}%",
                        'percentage_value' => $leaveAndAbsencePct,
                        'color'            => '#3B82F6', // Light Blue
                    ],
                ],
            ],

            // ── Card 2: Attendance Overview Widget (Right Card) ────────────────
            'attendance_overview_card' => [
                'card_title'      => 'Attendance Overview',
                'selected_filter' => $filterLabel,
                'donut_chart'     => [
                    'center_label' => 'Total Employee',
                    'center_value' => $totalEmployees,
                    'series'       => [
                        [
                            'label'            => 'Present Employee',
                            'count'            => $presentCount,
                            'percentage'       => "{$presentPct}%",
                            'percentage_value' => $presentPctDec,
                            'color'            => '#10B981', // Emerald / Green
                        ],
                        [
                            'label'            => 'Absent Employee',
                            'count'            => $absentCount,
                            'percentage'       => "{$absentPct}%",
                            'percentage_value' => $absentPctDec,
                            'color'            => '#6366F1', // Indigo / Purple
                        ],
                        [
                            'label'            => 'Leave',
                            'count'            => $leaveCount,
                            'percentage'       => "{$leavePct}%",
                            'percentage_value' => $leavePctDec,
                            'color'            => '#06B6D4', // Cyan
                        ],
                    ],
                ],
                'breakdown' => [
                    'present_employee' => [
                        'label'            => 'Present Employee',
                        'count'            => $presentCount,
                        'count_label'      => "{$presentCount} Employee",
                        'percentage'       => "{$presentPct}%",
                        'percentage_value' => $presentPctDec,
                        'color'            => '#10B981',
                    ],
                    'absent_employee' => [
                        'label'            => 'Absent Employee',
                        'count'            => $absentCount,
                        'count_label'      => "{$absentCount} Employee",
                        'percentage'       => "{$absentPct}%",
                        'percentage_value' => $absentPctDec,
                        'color'            => '#6366F1',
                    ],
                    'leave' => [
                        'label'            => 'Leave',
                        'count'            => $leaveCount,
                        'count_label'      => "{$leaveCount} Employee",
                        'percentage'       => "{$leavePct}%",
                        'percentage_value' => $leavePctDec,
                        'color'            => '#06B6D4',
                    ],
                    'leave_and_absence' => [
                        'label'            => 'Leave & Absence',
                        'count'            => $leaveAndAbsenceCount,
                        'count_label'      => "{$leaveAndAbsenceCount} Employee",
                        'percentage'       => "{$leaveAndAbsencePct}%",
                        'percentage_value' => $leaveAndAbsencePctDec,
                        'color'            => '#F59E0B', // Amber
                    ],
                ],
            ],

            // ── Raw metrics for additional consumption ──────────────────────────
            'summary' => [
                'total_employees'        => $totalEmployees,
                'present_count'          => $presentCount,
                'absent_count'           => $absentCount,
                'leave_count'            => $leaveCount,
                'leave_and_absence_count'=> $leaveAndAbsenceCount,
                'on_time_count'          => $onTimeCount,
                'late_count'             => $lateCount,
            ],
        ];
    }
}
