<?php

namespace App\Console\Commands;

use App\Models\HrmEmployee;
use App\Models\HrmLeaveBalance;
use App\Models\HrmLeavePolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateAnnualLeaveBalances extends Command
{
    protected $signature = 'hrm:generate-annual-leave-balances {--year= : The leave year to generate}';

    protected $description = 'Generate annual leave balances from leave policies for active employees';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $policiesByOrganization = HrmLeavePolicy::query()
            ->whereHas('leaveType', fn ($query) => $query->where('is_active', true))
            ->get()
            ->groupBy('organization_id');

        foreach ($policiesByOrganization as $organizationId => $policies) {
            if (!$organizationId) {
                $this->warn('Skipped leave policies without an organization.');
                $skipped += $policies->count();
                continue;
            }

            $policiesByLeaveType = $policies->groupBy('leave_type_id');

            HrmEmployee::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->chunkById(500, function ($employees) use (
                    $policiesByLeaveType,
                    $organizationId,
                    $year,
                    &$created,
                    &$updated
                ) {
                    DB::transaction(function () use (
                        $employees,
                        $policiesByLeaveType,
                        $organizationId,
                        $year,
                        &$created,
                        &$updated
                    ) {
                        foreach ($employees as $employee) {
                            foreach ($policiesByLeaveType as $leaveTypePolicies) {
                                $policy = $leaveTypePolicies->firstWhere('employee_type_id', $employee->employee_type_id)
                                    ?? $leaveTypePolicies->firstWhere('employee_type_id', null);

                                if (!$policy) {
                                    continue;
                                }

                                $balance = HrmLeaveBalance::query()->updateOrCreate(
                                    [
                                        'organization_id' => $organizationId,
                                        'hrm_employee_id' => $employee->id,
                                        'leave_type_id' => $policy->leave_type_id,
                                        'year' => $year,
                                    ],
                                    [
                                        'company_id' => $employee->company_id,
                                        'total_days' => $policy->leave_balance,
                                    ]
                                );

                                $balance->wasRecentlyCreated ? $created++ : $updated++;
                            }
                        }
                    });
                });
        }

        $this->info("Annual leave balances generated for {$year}. Created: {$created}; updated: {$updated}; skipped policies: {$skipped}.");

        return self::SUCCESS;
    }
}
