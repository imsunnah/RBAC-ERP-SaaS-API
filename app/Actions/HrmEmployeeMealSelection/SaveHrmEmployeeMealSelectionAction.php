<?php

namespace App\Actions\HrmEmployeeMealSelection;

use App\Models\HrmEmployeeMealSelection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaveHrmEmployeeMealSelectionAction
{
    public function execute(array $validated): mixed
    {
        $org = app('current_organization');
        $userId = Auth::id();

        return DB::transaction(function () use ($validated, $org, $userId) {
            // Case 1: Bulk Selections
            if (isset($validated['selections']) && is_array($validated['selections'])) {
                $results = [];
                foreach ($validated['selections'] as $selectionData) {
                    $selectedItemName = '-';
                    if ($selectionData['selection'] !== 'none') {
                        $meal = \App\Models\HrmWeeklyMeal::find($selectionData['hrm_weekly_meal_id']);
                        if ($meal) {
                            $selectedItemName = $selectionData['selection'] === 'standard' 
                                ? $meal->lunch_item 
                                : $meal->alternative_lunch;
                        }
                    }

                    $results[] = HrmEmployeeMealSelection::updateOrCreate(
                        [
                            'organization_id'    => $org?->id,
                            'hrm_employee_id'    => $validated['hrm_employee_id'],
                            'date'               => $selectionData['date'],
                        ],
                        [
                            'company_id'          => $org?->company_id,
                            'hrm_weekly_meal_id' => $selectionData['hrm_weekly_meal_id'],
                            'selection'           => $selectionData['selection'],
                            'selected_item_name'  => $selectedItemName,
                            'is_manually_updated' => true,
                            'updated_by'          => $userId,
                        ]
                    );
                }
                return $results;
            }

            // Case 2: Single Selection
            $selectedItemName = '-';
            if ($validated['selection'] !== 'none') {
                $meal = \App\Models\HrmWeeklyMeal::find($validated['hrm_weekly_meal_id']);
                if ($meal) {
                    $selectedItemName = $validated['selection'] === 'standard' 
                        ? $meal->lunch_item 
                        : $meal->alternative_lunch;
                }
            }

            return HrmEmployeeMealSelection::updateOrCreate(
                [
                    'organization_id'    => $org?->id,
                    'hrm_employee_id'    => $validated['hrm_employee_id'],
                    'date'               => $validated['date'],
                ],
                [
                    'company_id'          => $org?->company_id,
                    'hrm_weekly_meal_id' => $validated['hrm_weekly_meal_id'],
                    'selection'           => $validated['selection'],
                    'selected_item_name'  => $selectedItemName,
                    'is_manually_updated' => true,
                    'updated_by'          => $userId,
                ]
            );
        });
    }
}
