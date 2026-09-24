<?php

namespace App\Actions\HrmAttendance;

use App\Models\HrmEmployee;
use App\Models\HrmAttendance;
use App\Models\HrmLeaveApplication;
use App\Models\HrmHoliday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $isGlobal = !empty($filters['is_global']);
        
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
        if ($isGlobal) {
            $todayAttendanceQuery = HrmAttendance::whereDate('date', $today->toDateString())
                ->whereNotNull('check_in');
            if ($employee->organization_id) {
                $todayAttendanceQuery->where(function ($q) use ($employee) {
                    $q->where('organization_id', $employee->organization_id)
                      ->orWhereNull('organization_id');
                });
            }
            $todayAttendance = (clone $todayAttendanceQuery)->latest('check_in')->first();
            if (!$todayAttendance) {
                $fallbackQuery = HrmAttendance::whereDate('date', $today->toDateString());
                if ($employee->organization_id) {
                    $fallbackQuery->where(function ($q) use ($employee) {
                        $q->where('organization_id', $employee->organization_id)
                          ->orWhereNull('organization_id');
                    });
                }
                $todayAttendance = $fallbackQuery->latest()->first();
            }
        } else {
            $todayAttendance = HrmAttendance::where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
                if (!empty($employee->biometric_id)) {
                    $q->orWhere('biometric_id', $employee->biometric_id);
                }
                if (!empty($employee->user_employee_id)) {
                    $q->orWhere('biometric_id', $employee->user_employee_id);
                }
            })
            ->whereDate('date', $today->format('Y-m-d'))
            ->first();
        }

        $shift = $employee->workShift;
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

        $totalEmployeesQuery = HrmEmployee::where('is_active', true);
        if ($employee->organization_id) {
            $totalEmployeesQuery->where('organization_id', $employee->organization_id);
        }
        $totalEmployees = $totalEmployeesQuery->count();

        // Late & Half Day count as present
        $activeBioIds = HrmEmployee::where('is_active', true)->pluck('biometric_id')->filter()->toArray();
        $activeUserEmpIds = HrmEmployee::where('is_active', true)->pluck('user_employee_id')->filter()->toArray();

        $presentEmployeesQuery = HrmAttendance::whereDate('date', $today->toDateString())
            ->whereIn(DB::raw('UPPER(status)'), ['PRESENT', 'LATE', 'HALF_DAY', 'HALF DAY', 'ON_TIME'])
            ->where(function ($q) use ($activeBioIds, $activeUserEmpIds) {
                $q->whereHas('employee', fn ($query) => $query->where('is_active', true));
                if (!empty($activeBioIds)) {
                    $q->orWhereIn('biometric_id', $activeBioIds);
                }
                if (!empty($activeUserEmpIds)) {
                    $q->orWhereIn('biometric_id', $activeUserEmpIds);
                }
            });
        if ($employee->organization_id) {
            $presentEmployeesQuery->where(function ($q) use ($employee) {
                $q->where('organization_id', $employee->organization_id)
                  ->orWhereNull('organization_id');
            });
        }
        $presentEmployees = $presentEmployeesQuery->distinct('employee_id')->count('employee_id');

        $leaveEmployeesQuery = HrmLeaveApplication::where('status', 'approved')
            ->whereDate('from_date', '<=', $today->toDateString())
            ->whereDate('to_date', '>=', $today->toDateString())
            ->whereHas('employee', fn ($query) => $query->where('is_active', true));
        if ($employee->organization_id) {
            $leaveEmployeesQuery->where('organization_id', $employee->organization_id);
        }
        $leaveEmployees = $leaveEmployeesQuery->distinct('hrm_employee_id')->count('hrm_employee_id');

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

        if ($isGlobal) {
            $attQuery = HrmAttendance::whereBetween('date', [$queryStartDate, $queryEndDate])
                ->whereHas('employee', fn ($q) => $q->where('is_active', true));
            if ($employee->organization_id) {
                $attQuery->where(function ($q) use ($employee) {
                    $q->where('organization_id', $employee->organization_id)
                      ->orWhereNull('organization_id');
                });
            }
            $attendances = $attQuery->get();

            $leaveQuery = HrmLeaveApplication::where('status', 'approved')
                ->whereHas('employee', fn ($q) => $q->where('is_active', true))
                ->where(function ($q) use ($queryStartDate, $queryEndDate) {
                    $q->whereBetween('from_date', [$queryStartDate, $queryEndDate])
                      ->orWhereBetween('to_date', [$queryStartDate, $queryEndDate])
                      ->orWhere(function ($sub) use ($queryStartDate, $queryEndDate) {
                          $sub->where('from_date', '<=', $queryStartDate)
                              ->where('to_date', '>=', $queryEndDate);
                      });
                });
            if ($employee->organization_id) {
                $leaveQuery->where('organization_id', $employee->organization_id);
            }
            $leaves = $leaveQuery->get();
        } else {
            $attendances = HrmAttendance::where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
                if (!empty($employee->biometric_id)) {
                    $q->orWhere('biometric_id', $employee->biometric_id);
                }
                if (!empty($employee->user_employee_id)) {
                    $q->orWhere('biometric_id', $employee->user_employee_id);
                }
            })
            ->whereBetween('date', [$queryStartDate, $queryEndDate])
            ->get();

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
        }

        $attendancesByDate = $attendances->groupBy(function ($a) {
            $d = method_exists($a, 'getRawOriginal') ? $a->getRawOriginal('date') : null;
            if (!$d) {
                if ($a->date instanceof \Carbon\CarbonInterface) {
                    $d = $a->date->format('Y-m-d');
                } else {
                    $d = (string) $a->date;
                }
            }
            return substr((string) $d, 0, 10);
        });

        $holidayQuery = HrmHoliday::where('is_active', 1)
            ->whereBetween('date', [$queryStartDate, $queryEndDate]);
        if ($employee->organization_id) {
            $holidayQuery->where('organization_id', $employee->organization_id);
        }
        $holidays = $holidayQuery->get();

        // 4. Calculate Monthly Overview
        $isStatusPresent = function ($status) {
            $clean = strtoupper(trim(str_replace(['-', ' '], '_', (string) $status)));
            $clean = preg_replace('/_+/', '_', trim($clean, '_'));
            return in_array($clean, ['PRESENT', 'LATE', 'HALF_DAY', 'ON_TIME']);
        };

        $presentCount = 0;
        $absentCount = 0;
        $leaveCount = 0;
        $holidayCount = 0;
        $weeklyHolidayCount = 0;

        if ($isGlobal) {
            for ($day = 1; $day <= $totalDaysInMonth; $day++) {
                $currentDate = $startOfMonth->copy()->day($day);
                $dateStr = $currentDate->format('Y-m-d');
                $dayOfWeek = $currentDate->dayOfWeek; // 0=Sunday, 5=Friday, 6=Saturday
                
                $isWeekend = ($dayOfWeek === 5 || $dayOfWeek === 6); // Friday, Saturday

                $hasLeave = $leaves->first(function ($leave) use ($dateStr) {
                    return $dateStr >= $leave->from_date && $dateStr <= $leave->to_date;
                });

                $hasHoliday = $holidays->first(function ($holiday) use ($dateStr) {
                    return $holiday->date && $holiday->date->format('Y-m-d') === $dateStr;
                });

                $dayAtts = $attendancesByDate->get($dateStr, collect());
                $presentRecords = $dayAtts->filter(fn ($att) => $isStatusPresent($att->status));
                $absentRecords = $dayAtts->filter(fn ($att) => !$isStatusPresent($att->status));

                if ($presentRecords->isNotEmpty()) {
                    $presentCount += $presentRecords->count();
                } elseif ($absentRecords->isNotEmpty()) {
                    $absentCount += $absentRecords->count();
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
        } else {
            $presentCount = 0;

            for ($day = 1; $day <= $totalDaysInMonth; $day++) {
                $currentDate = $startOfMonth->copy()->day($day);
                $dateStr = $currentDate->format('Y-m-d');
                $dayOfWeek = $currentDate->dayOfWeek; // 0=Sunday, 5=Friday, 6=Saturday
                
                $isWeekend = ($dayOfWeek === 5 || $dayOfWeek === 6);

                $hasLeave = $leaves->first(function ($leave) use ($dateStr) {
                    return $dateStr >= $leave->from_date && $dateStr <= $leave->to_date;
                });

                $hasHoliday = $holidays->first(function ($holiday) use ($dateStr) {
                    return $holiday->date && $holiday->date->format('Y-m-d') === $dateStr;
                });

                $dayAtts = $attendancesByDate->get($dateStr, collect());
                $presentRecords = $dayAtts->filter(fn ($att) => $isStatusPresent($att->status));

                if ($presentRecords->isNotEmpty()) {
                    $presentCount++;
                } elseif ($dayAtts->isNotEmpty()) {
                    $absentCount++;
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
        }

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

            $dayAtts = $attendancesByDate->get($dateStr, collect());
            $presentRecords = $dayAtts->filter(fn ($att) => $isStatusPresent($att->status));
            
            $status = 'Absent';
            $hours = '00:00';
            $color = 'orange';

            if ($presentRecords->isNotEmpty()) {
                $hasHalfDay = false;
                $workingSecs = 0;

                foreach ($presentRecords as $att) {
                    $st = strtoupper(trim(str_replace(['-', ' '], '_', (string) $att->status)));
                    if ($st === 'HALF_DAY') {
                        $hasHalfDay = true;
                    }

                    if (!empty($att->working_hours)) {
                        $workingSecs += (float) $att->working_hours * 3600;
                    } elseif ($att->check_in && $att->check_out) {
                        $workingSecs += Carbon::parse($att->check_in)->diffInSeconds(Carbon::parse($att->check_out));
                    } elseif ($att->check_in) {
                        $workingSecs += Carbon::parse($att->check_in)->diffInSeconds(Carbon::now());
                    }
                }

                $hours = sprintf('%02d:%02d', floor($workingSecs / 3600), floor(($workingSecs / 60) % 60));

                if ($hasHalfDay && $presentRecords->count() === 1) {
                    $status = 'Half day';
                    $color = 'yellow';
                } else {
                    $status = 'Present';
                    $color = 'blue';
                }
            } elseif ($dayAtts->isNotEmpty()) {
                $status = 'Absent';
                $color = 'orange';
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


