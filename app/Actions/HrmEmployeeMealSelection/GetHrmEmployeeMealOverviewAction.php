<?php

namespace App\Actions\HrmEmployeeMealSelection;

use App\Models\HrmEmployee;
use App\Models\HrmEmployeeMealSelection;
use App\Models\User;
use Carbon\Carbon;

class GetHrmEmployeeMealOverviewAction
{
    /**
     * Execute the retrieval and calculation of the meal overview statistics.
     */
    public function execute(User $user, array $params): array
    {
        // Base query builder
        $baseQuery = HrmEmployeeMealSelection::query();

        // Filter based on super admin status and request parameters
        if (!$user->is_super_admin) {
            $baseQuery->where('hrm_employee_id', $user->employee_id ?: 0);
        } else {
            if (!empty($params['emp_uuid'])) {
                $employeeId = HrmEmployee::where('uuid', $params['emp_uuid'])->value('id');
                $baseQuery->where('hrm_employee_id', $employeeId ?: 0);
            }
        }

        // Determine date ranges
        $customRange = false;
        if (!empty($params['start_date']) && !empty($params['end_date'])) {
            try {
                $startInput = $params['start_date'];
                $endInput = $params['end_date'];

                $startCurrent = preg_match('/^\d{2}-\d{2}-\d{4}$/', $startInput)
                    ? Carbon::createFromFormat('d-m-Y', $startInput)
                    : Carbon::parse($startInput);

                $endCurrent = preg_match('/^\d{2}-\d{2}-\d{4}$/', $endInput)
                    ? Carbon::createFromFormat('d-m-Y', $endInput)
                    : Carbon::parse($endInput);

                $daysDiff = $startCurrent->diffInDays($endCurrent) + 1;
                $startPrevious = $startCurrent->copy()->subDays($daysDiff);
                $endPrevious = $startCurrent->copy()->subDay();

                $customRange = true;
            } catch (\Exception $e) {
                // Fallback to default month ranges
            }
        }

        if ($customRange) {
            $takenCurrentRange = [$startCurrent->format('Y-m-d'), $endCurrent->format('Y-m-d')];
            $takenPreviousRange = [$startPrevious->format('Y-m-d'), $endPrevious->format('Y-m-d')];

            $skippedCurrentRange = $takenCurrentRange;
            $skippedPreviousRange = $takenPreviousRange;

            $optedOutCurrentRange = $takenCurrentRange;
            $optedOutPreviousRange = $takenPreviousRange;

            $takenPeriodLabel = 'From Previous Period';
            $skippedPeriodLabel = 'From Previous Period';
            $optedOutPeriodLabel = 'From Previous Period';
        } else {
            // Default Current & Previous Month Ranges
            $startOfCurrentMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endOfCurrentMonth   = Carbon::now()->endOfMonth()->format('Y-m-d');

            $startOfLastMonth    = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
            $endOfLastMonth      = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');

            $takenCurrentRange = [$startOfCurrentMonth, $endOfCurrentMonth];
            $takenPreviousRange = [$startOfLastMonth, $endOfLastMonth];

            $skippedCurrentRange = [$startOfCurrentMonth, $endOfCurrentMonth];
            $skippedPreviousRange = [$startOfLastMonth, $endOfLastMonth];

            $optedOutCurrentRange = [
                Carbon::now()->subDays(29)->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ];
            $optedOutPreviousRange = [
                Carbon::now()->subDays(59)->format('Y-m-d'),
                Carbon::now()->subDays(30)->format('Y-m-d')
            ];

            $takenPeriodLabel = 'From Last Month';
            $skippedPeriodLabel = 'From Last Month';
            $optedOutPeriodLabel = 'Last 30 Days';
        }

        // 1. Taken Meals (anything other than 'none' and 'opted_out')
        $takenCurrent = (clone $baseQuery)
            ->whereNotIn('selection', ['none', 'opted_out'])
            ->whereBetween('date', $takenCurrentRange)
            ->count();

        $takenPrevious = (clone $baseQuery)
            ->whereNotIn('selection', ['none', 'opted_out'])
            ->whereBetween('date', $takenPreviousRange)
            ->count();

        // 2. Skipped Meals ('none')
        $skippedCurrent = (clone $baseQuery)
            ->where('selection', 'none')
            ->whereBetween('date', $skippedCurrentRange)
            ->count();

        $skippedPrevious = (clone $baseQuery)
            ->where('selection', 'none')
            ->whereBetween('date', $skippedPreviousRange)
            ->count();

        // 3. Opted Out Meals ('opted_out')
        $optedOutCurrent = (clone $baseQuery)
            ->where('selection', 'opted_out')
            ->whereBetween('date', $optedOutCurrentRange)
            ->count();

        $optedOutPrevious = (clone $baseQuery)
            ->where('selection', 'opted_out')
            ->whereBetween('date', $optedOutPreviousRange)
            ->count();

        // Prepare response array
        return [
            [
                'id'                => 'taken_meal',
                'title'             => 'Taken Meal',
                'count'             => $takenCurrent,
                'percentage_change' => $this->calculatePercentageChange($takenPrevious, $takenCurrent),
                'change_type'       => $takenCurrent >= $takenPrevious ? 'increase' : 'decrease',
                'period_label'      => $takenPeriodLabel,
                'icon'              => 'user-check',
            ],
            [
                'id'                => 'skipped_meal',
                'title'             => 'Skipped Meal',
                'count'             => $skippedCurrent,
                'percentage_change' => $this->calculatePercentageChange($skippedPrevious, $skippedCurrent),
                'change_type'       => $skippedCurrent >= $skippedPrevious ? 'increase' : 'decrease',
                'period_label'      => $skippedPeriodLabel,
                'icon'              => 'user-x',
            ],
            [
                'id'                => 'opted_out_meal',
                'title'             => 'Opted out Meal',
                'count'             => $optedOutCurrent,
                'percentage_change' => $this->calculatePercentageChange($optedOutPrevious, $optedOutCurrent),
                'change_type'       => $optedOutCurrent >= $optedOutPrevious ? 'increase' : 'decrease',
                'period_label'      => $optedOutPeriodLabel,
                'icon'              => 'user-minus',
            ],
        ];
    }

    /**
     * Helper to compute percentage growth/decline string.
     */
    private function calculatePercentageChange($previous, $current): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $formatted = sprintf('%02d', abs(round($change)));

        return ($change >= 0 ? '+' : '-') . $formatted . '%';
    }
}
