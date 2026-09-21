<?php

namespace App\Services\HrmLeave;

use App\Models\HrmHoliday;
use App\Models\HrmLeaveApplication;
use App\Models\HrmLeaveBalance;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveCalculationService
{
    public function calculateDays(Carbon $from, Carbon $to, ?string $startSession = null, ?string $endSession = null): float
    {
        if (!$from->isSameYear($to)) {
            throw ValidationException::withMessages(['to_date' => ['A leave application must remain within one leave year.']]);
        }

        $days = 0.0;
        $holidays = HrmHoliday::query()
            ->where('is_active', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $weekendDays = config('hrm.leave_weekend_days', [Carbon::SATURDAY, Carbon::SUNDAY]);
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if (in_array($date->dayOfWeek, $weekendDays, true) || $holidays->has($date->toDateString())) {
                continue;
            }
            $days++;
        }

        if ($days > 0 && $from->isSameDay($to) && (($startSession && $startSession !== 'full_day') || ($endSession && $endSession !== 'full_day'))) {
            $days = 0.5;
        } else {
            if ($startSession && $startSession !== 'full_day' && $this->isWorkingDay($from, $holidays, $weekendDays)) {
                $days -= 0.5;
            }
            if ($endSession && $endSession !== 'full_day' && !$from->isSameDay($to) && $this->isWorkingDay($to, $holidays, $weekendDays)) {
                $days -= 0.5;
            }
        }

        if ($days <= 0) {
            throw ValidationException::withMessages(['from_date' => ['The selected period contains no working days.']]);
        }

        return $days;
    }

    public function validateApplication(HrmLeaveApplication $application, float $requestedDays): void
    {
        $overlap = HrmLeaveApplication::query()
            ->where('hrm_employee_id', $application->hrm_employee_id)
            ->where($application->getKeyName(), '<>', $application->getKey())
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $application->to_date)
            ->whereDate('to_date', '>=', $application->from_date)
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'from_date' => ["Leave application overlaps with an existing leave from {$overlap->from_date->format('Y-m-d')} to {$overlap->to_date->format('Y-m-d')}"],
            ]);
        }

        $balance = HrmLeaveBalance::query()
            ->where('hrm_employee_id', $application->hrm_employee_id)
            ->where('leave_type_id', $application->leave_type_id)
            ->where('year', $application->from_date->year)
            ->lockForUpdate()
            ->first();

        if (!$balance) {
            throw ValidationException::withMessages(['leave_type_id' => ['No leave balance exists for this employee and leave year.']]);
        }

        $available = (float) $balance->total_days - (float) $balance->used_days - (float) ($balance->pending_days ?? 0);
        if ($available < $requestedDays) {
            throw ValidationException::withMessages(['from_date' => ['Insufficient leave balance.']]);
        }
    }

    private function isWorkingDay(Carbon $date, $holidays, array $weekendDays): bool
    {
        return !in_array($date->dayOfWeek, $weekendDays, true) && !$holidays->has($date->toDateString());
    }
}