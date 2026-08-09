<?php

namespace App\Actions\HrmWeeklyMeal;

use App\Models\HrmEmployee;
use App\Models\HrmWeeklyMeal;
use Illuminate\Support\Facades\Auth;

class UpdateHrmWeeklyMealAction
{
    /**
     * Execute update of a weekly meal setup.
     */
    public function execute(HrmWeeklyMeal $meal, array $validated): HrmWeeklyMeal
    {
        $updateData = [];

        if (isset($validated['day'])) {
            $updateData['day'] = strtolower($validated['day']);
        }
        if (isset($validated['lunch_item'])) {
            $updateData['lunch_item'] = $validated['lunch_item'];
        }
        if (array_key_exists('alternative_lunch', $validated)) {
            $updateData['alternative_lunch'] = $validated['alternative_lunch'];
        }
        if (isset($validated['expected_person'])) {
            $updateData['expected_person'] = (int) $validated['expected_person'];
        }
        if (array_key_exists('chef_id', $validated)) {
            $updateData['chef_id'] = !empty($validated['chef_id'])
                ? (is_numeric($validated['chef_id']) ? (int) $validated['chef_id'] : HrmEmployee::where('uuid', $validated['chef_id'])->value('id'))
                : null;
        }
        if (array_key_exists('chef_name', $validated)) {
            $updateData['chef_name'] = $validated['chef_name'];
        }
        if (isset($validated['is_active'])) {
            $updateData['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $updateData['updated_by'] = Auth::id();

        $meal->update($updateData);

        return $meal->load('chef');
    }
}
