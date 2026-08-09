<?php

namespace App\Actions\HrmHoliday;

use App\Models\HrmHoliday;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GetHrmHolidayCalendarAction
{
    /**
     * Execute calendar calculation for a given year and month.
     */
    public function execute(Request $request): array
    {
        $year  = (int) $request->year;
        $month = (int) $request->month;

        $shift = null;
        if ($request->filled('shift_id')) {
            $shiftId = $request->shift_id;
            $shift = WorkShift::where(function ($q) use ($shiftId) {
                if (is_numeric($shiftId)) {
                    $q->where('shift_id', $shiftId);
                }
                $q->orWhere('uuid', $shiftId);
            })->first();
        }

        if (!$shift) {
            $shift = WorkShift::where('is_default', true)->where('is_active', true)->first();
        }

        if (!$shift) {
            $shift = WorkShift::where('is_active', true)->first();
        }

        $rawWeekend = $shift && is_array($shift->weekend) ? $shift->weekend : [];
        $weekendDays = array_values(array_map('strtolower', array_map('trim', $rawWeekend)));

        $holidays = HrmHoliday::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('is_active', true)
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($h) => $h->date ? $h->date->format('Y-m-d') : '');

        $startOfMonth = Carbon::createFromDate($year, $month, 1);
        $daysInMonth  = $startOfMonth->daysInMonth;

        $calendar = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date        = Carbon::createFromDate($year, $month, $day);
            $dateStr     = $date->format('Y-m-d');
            $dayName     = strtolower($date->format('l'));

            $isWeekend   = in_array($dayName, $weekendDays, true);
            $dayHolidays = $holidays->get($dateStr, collect());

            $calendar[] = [
                'date'       => $dateStr,
                'day'        => $dayName,
                'is_weekend' => $isWeekend,
                'is_holiday' => $dayHolidays->isNotEmpty(),
                'holidays'   => $dayHolidays->map(fn ($h) => [
                    'uuid'   => $h->uuid,
                    'name'   => $h->name,
                ])->values(),
            ];
        }

        return [
            'year'         => $year,
            'month'        => $month,
            'work_shift'   => $shift ? [
                'shift_id'   => $shift->shift_id,
                'uuid'       => $shift->uuid,
                'name'       => $shift->name,
                'short_code' => $shift->short_code,
            ] : null,
            'weekend_days' => $weekendDays,
            'calendar'     => $calendar,
        ];
    }
}
