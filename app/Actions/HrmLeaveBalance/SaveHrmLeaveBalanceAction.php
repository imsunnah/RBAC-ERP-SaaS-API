<?php

namespace App\Actions\HrmLeaveBalance;

use App\Models\HrmLeaveBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveHrmLeaveBalanceAction
{
    /**
     * Execute the leave balance creation or update (supports single or bulk).
     */
    public function execute(array $validated): mixed
    {
        $org = app('current_organization');
        $userId = Auth::id();

        return DB::transaction(function () use ($validated, $org, $userId) {
            // Case 1: Bulk Save
            if (isset($validated['balances']) && is_array($validated['balances'])) {
                $results = [];
                foreach ($validated['balances'] as $balanceData) {
                    $results[] = HrmLeaveBalance::updateOrCreate(
                        [
                            'organization_id' => $org?->id,
                            'hrm_employee_id' => $validated['hrm_employee_id'],
                            'leave_type_id'   => $balanceData['leave_type_id'],
                            'year'            => $validated['year'],
                        ],
                        [
                            'company_id' => $org?->company_id,
                            'total_days' => $balanceData['total_days'],
                            'updated_by' => $userId,
                        ]
                    );
                }
                return $results;
            }

            // Case 2: Single Save
            return HrmLeaveBalance::updateOrCreate(
                [
                    'organization_id' => $org?->id,
                    'hrm_employee_id' => $validated['hrm_employee_id'],
                    'leave_type_id'   => $validated['leave_type_id'],
                    'year'            => $validated['year'],
                ],
                [
                    'company_id' => $org?->company_id,
                    'total_days' => $validated['total_days'],
                    'updated_by' => $userId,
                ]
            );
        });
    }
}
