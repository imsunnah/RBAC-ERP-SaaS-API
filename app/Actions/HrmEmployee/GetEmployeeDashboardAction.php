<?php

namespace App\Actions\HrmEmployee;

use App\Models\HrmAttendance;
use App\Models\HrmEmployee;
use App\Models\GlobalLookup;
use App\Models\HrmLookup;
use App\Models\HrmLeaveApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetEmployeeDashboardAction
{
    /**
     * Execute KPI calculations and employee type distribution for the dashboard.
     */
    public function execute(): array
    {
        $todayStr         = Carbon::today()->toDateString();
        $yesterdayStr     = Carbon::yesterday()->toDateString();
        $startOfMonth     = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth   = Carbon::now()->subMonth()->endOfMonth();
        $sub30Days        = Carbon::now()->subDays(30);

        // 1. Total Employees & 30-day Growth
        $totalEmployees     = HrmEmployee::where('is_active', true)->count();
        $employees30DaysAgo = HrmEmployee::where('is_active', true)
            ->where('created_at', '<=', $sub30Days)
            ->count();

        $totalDiff      = $totalEmployees - $employees30DaysAgo;
        $totalGrowthPct = $employees30DaysAgo > 0
            ? round(($totalDiff / $employees30DaysAgo) * 100, 1)
            : ($totalEmployees > 0 ? 100 : 0);

        // 2. New Employees & Month-Over-Month Growth
        $newEmployeesThisMonth = HrmEmployee::where('created_at', '>=', $startOfMonth)->where('is_active', true)->count();
        $newEmployeesLastMonth = HrmEmployee::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

        $newDiff      = $newEmployeesThisMonth - $newEmployeesLastMonth;
        $newGrowthPct = $newEmployeesLastMonth > 0
            ? round(($newDiff / $newEmployeesLastMonth) * 100, 1)
            : ($newEmployeesThisMonth > 0 ? 100 : 0);

        // 3. Present Today & Day-Over-Day Comparison
        $presentToday =HrmAttendance::whereDate('date', $todayStr)
            ->whereIn(DB::raw('UPPER(status)'), [HrmAttendance::STATUS_PRESENT, HrmAttendance::STATUS_LATE])
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->distinct('employee_id')
            ->count('employee_id');

        $presentYesterday = HrmAttendance::whereDate('date', $yesterdayStr)
            ->whereIn('status', [HrmAttendance::STATUS_PRESENT, HrmAttendance::STATUS_LATE])
            ->count();

        $presentDiff      = $presentToday - $presentYesterday;
        $presentGrowthPct = $presentYesterday > 0
            ? round(($presentDiff / $presentYesterday) * 100, 1)
            : ($presentToday > 0 ? 100 : 0);

        // 4. On Leave Employees Today & Day-Over-Day Comparison
        $onLeaveToday = HrmLeaveApplication::where('status', 'approved')
            ->whereDate('from_date', '<=', $todayStr)
            ->whereDate('to_date', '>=', $todayStr)
            ->count();

        $onLeaveYesterday = HrmLeaveApplication::where('status', 'approved')
            ->whereDate('from_date', '<=', $yesterdayStr)
            ->whereDate('to_date', '>=', $yesterdayStr)
            ->count();

        $leaveDiff      = $onLeaveToday - $onLeaveYesterday;
        $leaveGrowthPct = $onLeaveYesterday > 0
            ? round(($leaveDiff / $onLeaveYesterday) * 100, 1)
            : ($onLeaveToday > 0 ? 100 : 0);

        // 5. Employee Overview / Employment Type Breakdown (SQL aggregation — no N+1)
        $totalActiveCount = HrmEmployee::where('is_active', true)->count();

        $typeCounts = HrmEmployee::where('is_active', true)
            ->select('employee_type_id', DB::raw('COUNT(*) as count'))
            ->groupBy('employee_type_id')
            ->get();

        $typeIds      = $typeCounts->pluck('employee_type_id')->filter()->toArray();
        $typeNames    = GlobalLookup::whereIn('id', $typeIds)->pluck('name', 'id');
        $hrmTypeNames = HrmLookup::whereIn('id', $typeIds)->pluck('name', 'id');

        $colors       = ['#007bff', '#17a2b8', '#28a745', '#ffc107', '#6c757d', '#e83e8c'];
        $distribution = [];
        $index        = 0;

        foreach ($typeCounts as $row) {
            $typeName = $row->employee_type_id
                ? ($typeNames[$row->employee_type_id] ?? $hrmTypeNames[$row->employee_type_id] ?? 'Unassigned')
                : 'Unassigned';
            $pct = $totalActiveCount > 0 ? round(($row->count / $totalActiveCount) * 100, 2) : 0;

            $distribution[] = [
                'type'                       => $typeName,
                'count'                      => $row->count,
                'percentage'                 => $pct,
                'percentage_of_total'        => $pct,
                'percentage_of_total_string' => $pct . '%',
                'color'                      => $colors[$index % count($colors)],
            ];
            $index++;
        }

        // Day-over-day percentage changes for attendance and leave KPIs
        $presentPctOfTotal = $presentGrowthPct;
        $onLeavePctOfTotal = $leaveGrowthPct;
        $newPctOfTotal     = $totalActiveCount > 0 ? round(($newEmployeesThisMonth / $totalActiveCount) * 100, 2) : 0;

        return [
            'employee_summary' => [
                'total_employees' => [
                    'count'                      => $totalEmployees,
                    'growth_percentage'          => ($totalGrowthPct >= 0 ? '+' : '') . $totalGrowthPct . '%',
                    'period_label'               => 'Last 30 Days',
                    'percentage_of_total'        => 100.0,
                    'percentage_of_total_string' => '100%',
                ],
                'new_employees' => [
                    'count'                      => $newEmployeesThisMonth,
                    'growth_percentage'          => ($newGrowthPct >= 0 ? '+' : '') . $newGrowthPct . '%',
                    'period_label'               => 'From Last Month',
                    'percentage_of_total'        => $newPctOfTotal,
                    'percentage_of_total_string' => $newPctOfTotal . '%',
                ],
                'present_today' => [
                    'count'                      => $presentToday,
                    'growth_percentage'          => ($presentGrowthPct >= 0 ? '+' : '') . $presentGrowthPct . '%',
                    'period_label'               => 'From Previous Day',
                    'percentage_of_total'        => $presentPctOfTotal,
                    'percentage_of_total_string' => $presentPctOfTotal . '%',
                ],
                'on_leave_today' => [
                    'count'                      => $onLeaveToday,
                    'growth_percentage'          => ($leaveGrowthPct >= 0 ? '+' : '') . $leaveGrowthPct . '%',
                    'period_label'               => 'From Previous Day',
                    'percentage_of_total'        => $onLeavePctOfTotal,
                    'percentage_of_total_string' => $onLeavePctOfTotal . '%',
                ],
            ],
            'employee_overview' => [
                'total_count'       => $totalActiveCount,
                'growth_percentage' => ($totalGrowthPct >= 0 ? '+' : '') . $totalGrowthPct . '%',
                'period_label'      => 'From Last Month',
                'distribution'      => $distribution,
            ],
        ];
    }
}
