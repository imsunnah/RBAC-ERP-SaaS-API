<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrmAttendance\GetAttendanceAndLeaveSummaryAction;
use App\Actions\HrmAttendance\GetEmployeeDashboardSummaryAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\HrmAttendanceResource;
use App\Models\HrmAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HrmAttendanceController extends Controller
{
    /** Eager-load relations shared across index / store / update / show. */
    private const RELATIONS = [
        'employee.department',
        'employee.designation',
        'shift',
    ];

    /**
     * Display a listing of stored attendance records.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmAttendance::class);

        $request->validate([
            'date'       => ['nullable', 'date'],
            'search'     => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'uuid'],
            'per_page'   => ['nullable', 'integer'],
        ]);

        $query = HrmAttendance::with(self::RELATIONS)->latest('date');

        $user = $request->user();
        if (!$user->is_super_admin) {
            $query->where('employee_id', $user->employee_id ?: 0);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('department')) {
            $deptId = \App\Models\HrmLookup::where('uuid', $request->department)->value('id');
            if ($deptId) {
                $query->whereHas('employee', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            
            // Pre-fetch matching department and designation IDs to avoid Postgres type mismatch on varchar/bigint joins
            $matchingDeptIds = \App\Models\HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'department'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
                
            $matchingDesigIds = \App\Models\HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'designation'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->toArray();

            $query->where(function ($q) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                $q->where('status', 'like', "%{$searchTerm}%")
                  ->orWhereHas('shift', function ($shiftQ) use ($searchTerm) {
                      $shiftQ->where('name', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('employee', function ($empQ) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                      $empQ->where('full_name', 'like', "%{$searchTerm}%")
                           ->orWhere('user_employee_id', 'like', "%{$searchTerm}%");
                           
                      if (!empty($matchingDesigIds)) {
                          $empQ->orWhereIn('designation_id', $matchingDesigIds);
                      }
                      if (!empty($matchingDeptIds)) {
                          $empQ->orWhereIn('department_id', $matchingDeptIds);
                      }
                  });
            });
        }

        $attendances = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => HrmAttendanceResource::collection($attendances)->response()->getData(true),
        ]);
    }

    /**
     * Get detailed monthly attendance list and statistics for a specific employee.
     * Defaults to the current month and the authenticated user's corresponding employee.
     */
    public function employeeMonthlyDetails(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmAttendance::class);

        $validated = $request->validate([
            'employee_id' => ['nullable', 'uuid'],
            'month'       => ['nullable', 'string', 'regex:/^(\d{4}-\d{2}|\d{2}-\d{4})$/'],
        ]);

        $user = $request->user();

        $employeeUuid = $validated['employee_id'] ?? null;
        if ($employeeUuid) {
            $employee = \App\Models\HrmEmployee::with(['department', 'designation', 'workShift', 'workShiftLookup'])->where('uuid', $employeeUuid)->first();
        } else {
            // Find by employee_id, or fallback to the first employee in the organization context
            $employee = \App\Models\HrmEmployee::with(['department', 'designation', 'workShift', 'workShiftLookup'])
                ->find($user->employee_id);

            if (!$employee) {
                $employee = \App\Models\HrmEmployee::with(['department', 'designation', 'workShift', 'workShiftLookup'])
                    ->where('organization_id', $user->organization_id)
                    ->first();
            }
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.',
            ], 404);
        }

        $monthStr = $validated['month'] ?? null;
        if ($monthStr) {
            if (preg_match('/^\d{2}-\d{4}$/', $monthStr)) {
                $targetMonth = \Carbon\Carbon::createFromFormat('m-Y', $monthStr);
            } else {
                $targetMonth = \Carbon\Carbon::createFromFormat('Y-m', $monthStr);
            }
        } else {
            $targetMonth = now();
        }

        $startDate = $targetMonth->copy()->startOfMonth();
        $endDate = $targetMonth->copy()->endOfMonth();
        $totalDaysInMonth = $targetMonth->daysInMonth;

        $isCurrentMonth = ($targetMonth->format('Y-m') === now()->format('Y-m'));
        $isFutureMonth = ($targetMonth->format('Y-m') > now()->format('Y-m'));

        if ($isCurrentMonth) {
            $totalDaysInMonth = min($totalDaysInMonth, now()->day);
            $endDate = now(); // Limit query ranges up to today as well
        } elseif ($isFutureMonth) {
            $totalDaysInMonth = 0;
        }

        // Fetch approved leave applications that overlap with the target month
        $leaves = \App\Models\HrmLeaveApplication::where('hrm_employee_id', $employee->id)
            ->where('status', 'APPROVED')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('from_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhereBetween('to_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->where('from_date', '<=', $startDate->format('Y-m-d'))
                          ->where('to_date', '>=', $endDate->format('Y-m-d'));
                  });
            })
            ->get();

        // Fetch active holidays for this organization that overlap with the target month
        $holidays = \App\Models\HrmHoliday::where('organization_id', $employee->organization_id)
            ->where('is_active', 1)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        // Fetch summarized attendance records for this month
        $attendances = HrmAttendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($att) => \Carbon\Carbon::parse($att->date)->format('Y-m-d'));

        $calendar = [];
        $daysOffCount = 0;
        $lateCount = 0;
        $noClockOutCount = 0;
        $absentCount = 0;
        $leaveCount = 0;

        for ($day = 1; $day <= $totalDaysInMonth; $day++) {
            $currentDate = $startDate->copy()->addDays($day - 1);
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek; // 0 = Sunday, 5 = Friday, 6 = Saturday

            // Check if weekend (Friday & Saturday)
            $isWeekend = ($dayOfWeek === 5 || $dayOfWeek === 6);

            $holiday = $holidays->first(fn($h) => $h->date->format('Y-m-d') === $dateStr);
            $isHoliday = !empty($holiday);
            $holidayName = $isHoliday ? $holiday->name : null;

            // Check if leave
            $leaveApp = $leaves->first(fn($l) => $dateStr >= $l->from_date && $dateStr <= $l->to_date);
            $isLeave = !empty($leaveApp);
            $leaveType = $isLeave ? ($leaveApp->leaveType?->name ?? 'Leave') : null;

            if ($isWeekend || $isHoliday) {
                $daysOffCount++;
            }
            if ($isLeave) {
                $leaveCount++;
            }

            $attendance = $attendances->get($dateStr);
            $attendanceData = null;
            $workingMinutes = 0;

            // Attendance records use WorkShift, while employees store a GlobalLookup shift.
            $workShift = $attendance?->shift ?: $employee->workShift;
            $shiftName = $workShift?->name ?: $employee->workShiftLookup?->name;
            $shiftTimes = $this->parseShiftTimes($shiftName);
            $shiftStart = \Carbon\Carbon::parse($dateStr . ' ' . $shiftTimes['start']);
            $shiftEnd = \Carbon\Carbon::parse($dateStr . ' ' . $shiftTimes['end']);
            if ($shiftTimes['overnight'] && $shiftEnd->lessThanOrEqualTo($shiftStart)) {
                $shiftEnd->addDay();
            }
            $shiftStartTime = $shiftStart->format('h:i A');
            $shiftEndTime = $shiftEnd->format('h:i A');

            if ($attendance) {
                $status = $attendance->status;
                if ($status === HrmAttendance::STATUS_LATE) {
                    $lateCount++;
                } elseif ($status === HrmAttendance::STATUS_ABSENT) {
                    $absentCount++;
                }

                $checkIn = $attendance->check_in
                    ? $this->attendanceTimeOnDate($attendance->check_in, $dateStr)
                    : null;
                $checkOut = $attendance->check_out
                    ? $this->attendanceTimeOnDate($attendance->check_out, $dateStr)
                    : null;
                if ($checkIn && $checkOut && $checkOut->lessThan($checkIn)) {
                    $checkOut->addDay();
                }

                if ($checkIn && !$checkOut) {
                    $noClockOutCount++;
                }

                $durationStr = null;
                if ($checkIn && $checkOut) {
                    $durationDiff = $checkIn->diff($checkOut);
                    $durationStr = sprintf('%02dh %02dm', $durationDiff->h, $durationDiff->i);
                }

                // Calculate actual worked time and exclude the fixed 11:00-11:20 break.
                if ($checkIn) {
                    $workingMinutes = $checkOut
                        ? $checkIn->diffInMinutes($checkOut)
                        : (int) round(($attendance->working_hours ?: 0) * 60);
                    $breakStart = \Carbon\Carbon::parse($dateStr . ' 11:00:00');
                    $breakEnd = \Carbon\Carbon::parse($dateStr . ' 11:20:00');
                    if ($checkOut && $checkIn->lt($breakEnd) && $checkOut->gt($breakStart)) {
                        $workingMinutes -= $checkIn->max($breakStart)->diffInMinutes($checkOut->min($breakEnd));
                    }
                    $workingMinutes = max(0, $workingMinutes);
                }

                // Compile timeline events
                $timeline = [];
                if ($checkIn) {
                    // Check if late (red bar)
                    if ($checkIn->gt($shiftStart)) {
                        $lateMins = $shiftStart->diffInMinutes($checkIn);
                        $timeline[] = [
                            'label'    => 'Late',
                            'type'     => 'late',
                            'duration' => "{$lateMins}:00 m",
                            'color'    => '#F44336'
                        ];
                    }

                    $workingDurationStr = sprintf('%02d:%02d h', intdiv($workingMinutes, 60), $workingMinutes % 60);

                    $timeline[] = [
                        'label'    => 'Working Time',
                        'type'     => 'working',
                        'duration' => $workingDurationStr,
                        'color'    => '#2196F3'
                    ];

                    // Mock meeting for Sunday / Today to match mockup aesthetics
                    if ($dayOfWeek === 0 || $dateStr === now()->format('Y-m-d')) {
                        $timeline[] = [
                            'label'    => 'Meeting',
                            'type'     => 'meeting',
                            'duration' => '15:00 m',
                            'color'    => '#4CAF50'
                        ];
                    }

                    if (!$isWeekend && !$isHoliday && !$isLeave) {
                        $timeline[] = [
                            'label'    => 'Break',
                            'type'     => 'break',
                            'duration' => '20:00 m',
                            'color'    => '#C4D9CE'
                        ];
                    }
                }

                $attendanceData = [
                    'status'    => $status,
                    'check_in'  => $checkIn ? $checkIn->format('h:i A') : $shiftStartTime,
                    'check_out' => $checkOut ? $checkOut->format('h:i A') : null,
                    'duration'  => $workingMinutes > 0 ? sprintf('%02dh %02dm', intdiv($workingMinutes, 60), $workingMinutes % 60) : null,
                    'timeline'  => $timeline,
                    'badge'     => null,
                ];
            } else {
                // If it's a Weekend or Holiday:
                if ($isWeekend || $isHoliday) {
                    $attendanceData = [
                        'status'    => 'OFF_DAY',
                        'check_in'  => $shiftStartTime,
                        'check_out' => null,
                        'duration'  => null,
                        'timeline'  => [
                            [
                                'label'    => 'Off Days',
                                'type'     => 'off_day',
                                'duration' => '08:00 h',
                                'color'    => '#4CAF50'
                            ]
                        ],
                        'badge'     => [
                            'text'  => $isHoliday ? $holidayName : 'Weekend',
                            'color' => 'green'
                        ]
                    ];
                }
                // If it's a Leave day:
                elseif ($isLeave) {
                    $attendanceData = [
                        'status'    => 'LEAVE',
                        'check_in'  => $shiftStartTime,
                        'check_out' => null,
                        'duration'  => null,
                        'timeline'  => [
                            [
                                'label'    => 'on Leave',
                                'type'     => 'leave',
                                'duration' => '08:00 h',
                                'color'    => '#FFC107'
                            ]
                        ],
                        'badge'     => [
                            'text'  => 'Approved',
                            'color' => 'green'
                        ]
                    ];
                }
                // If it's an Absent day:
                else {
                    $absentCount++;
                    $attendanceData = [
                        'status'    => 'ABSENT',
                        'check_in'  => $shiftStartTime,
                        'check_out' => null,
                        'duration'  => null,
                        'timeline'  => [
                            [
                                'label'    => 'Absent',
                                'type'     => 'absent',
                                'duration' => '08:00 h',
                                'color'    => '#F44336'
                            ]
                        ],
                        'badge'     => [
                            'text'  => 'Absent',
                            'color' => 'red'
                        ]
                    ];
                }
            }

            $calendar[] = [
                'date'           => $dateStr,
                'day_name'       => $currentDate->format('l'),
                'formatted_date' => $currentDate->format('l, d'),
                'is_weekend'     => $isWeekend,
                'is_holiday'     => $isHoliday,
                'holiday_name'   => $holidayName,
                'is_leave'       => $isLeave,
                'leave_type'     => $leaveType,
                'attendance'     => $attendanceData,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'employee' => [
                    'uuid'        => $employee->uuid,
                    'name'        => $employee->full_name,
                    'employee_id' => $employee->user_employee_id,
                    'designation' => $employee->designation?->name ?? 'Employee',
                    'photo'       => $employee->profile_picture ? asset(Storage::url($employee->profile_picture)) : null,
                    'status'      => $employee->is_active ? 'Permanent' : 'Inactive',
                ],
                'stats' => [
                    'days_off'      => $daysOffCount,
                    'late_clock_in' => $lateCount,
                    'no_clock_out'  => $noClockOutCount,
                    'absent'        => $absentCount,
                    'leave'         => $leaveCount,
                ],
                'month' => [
                    'label' => $targetMonth->format('F Y'),
                    'value' => $targetMonth->format('Y-m'),
                ],
                'calendar' => $calendar,
            ]
        ]);
    }

    private function parseShiftTimes(?string $shiftName): array
    {
        $default = [
            'start' => '09:00:00',
            'end' => '17:00:00',
            'overnight' => false,
        ];

        if (!$shiftName || !preg_match(
            '/\((\d{1,2})(?::(\d{2}))?\s*(am|pm)\s*-\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)\)/i',
            $shiftName,
            $matches
        )) {
            return $default;
        }

        $start = \Carbon\Carbon::createFromFormat(
            'g:i A',
            sprintf('%d:%02d %s', $matches[1], $matches[2] ?? 0, strtoupper($matches[3]))
        );
        $end = \Carbon\Carbon::createFromFormat(
            'g:i A',
            sprintf('%d:%02d %s', $matches[4], $matches[5] ?? 0, strtoupper($matches[6]))
        );

        return [
            'start' => $start->format('H:i:s'),
            'end' => $end->format('H:i:s'),
            'overnight' => $end->lessThanOrEqualTo($start),
        ];
    }

    private function attendanceTimeOnDate(mixed $value, string $date): \Carbon\Carbon
    {
        $time = \Carbon\Carbon::parse($value)->format('H:i:s');

        return \Carbon\Carbon::parse($date . ' ' . $time);
    }


    /**
     * Push day-wise attendance log with in and out times for a specific employee.
     * Just for development / testing.
     */
    public function devPushDayAttendance(
        Request $request,
        \App\Actions\HrmAttendance\DevPushDayAttendanceAction $action
    ): JsonResponse {
        $this->authorize('viewAny', HrmAttendance::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'string'],
            'date'        => ['required', 'date_format:Y-m-d'],
            'check_in'    => ['nullable', 'date_format:H:i:s'],
            'check_out'   => ['nullable', 'date_format:H:i:s'],
            'status'      => ['nullable', 'string', 'in:present,absent,late,half_day,leave,holiday'],
        ]);

        $empIdentifier = $validated['employee_id'];

        $employee = \App\Models\HrmEmployee::where('uuid', $empIdentifier)
            ->orWhere('user_employee_id', $empIdentifier)
            ->orWhere('user_employee_id', 'like', "%{$empIdentifier}%")
            ->orWhere('id', is_numeric($empIdentifier) ? (int)$empIdentifier : 0)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => "Employee not found with identifier: {$empIdentifier}",
            ], 404);
        }

        $attendance = $action->execute($employee, $validated);

        return response()->json([
            'success' => true,
            'message' => "Successfully pushed day attendance record for employee {$employee->full_name}.",
            'employee' => [
                'uuid'             => $employee->uuid,
                'user_employee_id' => $employee->user_employee_id,
                'name'             => $employee->full_name,
            ],
            'attendance' => $attendance
        ]);
    }

    /**
     * Get detailed attendance dashboard summary for an employee (or logged-in user).
     * Maps to the "Office Attendance", "Attendance Overview", and "Hours Logged" dashboard widgets.
     */
    public function employeeDashboardSummary(
        Request $request,
        GetEmployeeDashboardSummaryAction $action
    ): JsonResponse {
        $this->authorize('viewAny', HrmAttendance::class);

        $validated = $request->validate([
            'employee_id' => ['nullable', 'uuid', 'exists:hrm_employees,uuid'],
            'month'       => ['nullable', 'string'],
            'date'        => ['nullable', 'date'],
            'from_date'   => ['nullable', 'date'],
            'to_date'     => ['nullable', 'date', 'after_or_equal:from_date'],
            'days'        => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $user = $request->user();

        // Find employee: Check request employee_id (UUID) first, fallback to logged-in user
        $employee = null;
        if ($request->filled('employee_id')) {
            $employee = \App\Models\HrmEmployee::with(['designation', 'workShift'])
                ->where('uuid', $request->input('employee_id'))
                ->first();
        } elseif ($user && $user->employee_id) {
            $employee = \App\Models\HrmEmployee::with(['designation', 'workShift'])
                ->find($user->employee_id);
        }

        // all employee for admin

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee record not found.',
            ], 404);
        }

        $summary = $action->execute($employee, $request->input('month'), $validated);

        return response()->json([
            'success' => true,
            'data'    => $summary
        ]);
    }

    /**
     * Get Attendance summary for both the Total Employee card and Attendance Overview card.
     */
    public function attendanceSummary(
        Request $request,
        GetAttendanceAndLeaveSummaryAction $action
    ): JsonResponse {
        $this->authorize('viewAny', HrmAttendance::class);

        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $data = $action->execute($request->input('date'));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
