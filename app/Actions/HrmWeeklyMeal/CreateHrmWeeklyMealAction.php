<?php

namespace App\Actions\HrmWeeklyMeal;

use App\Models\HrmEmployee;
use App\Models\HrmWeeklyMeal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateHrmWeeklyMealAction
{
    /**
     * Execute creation of single or bulk weekly meal setup.
     */
    public function execute(array $validated): mixed
    {
        $currentOrg = app('current_organization');
        $userId     = Auth::id();

        return DB::transaction(function () use ($validated, $currentOrg, $userId) {
            // Case 1: Bulk array of meals provided
            if (isset($validated['meals']) && is_array($validated['meals'])) {
                $results = [];
                foreach ($validated['meals'] as $item) {
                    $results[] = $this->createSingleMeal($item, $currentOrg, $userId);
                }
                return $results;
            }

            // Case 2: Single meal item
            return $this->createSingleMeal($validated, $currentOrg, $userId);
        });
    }

    /**
     * Create a single meal record.
     */
    private function createSingleMeal(array $data, ?object $currentOrg, ?int $userId): HrmWeeklyMeal
    {
        $chefId = null;
        if (!empty($data['chef_id'])) {
            $chefId = is_numeric($data['chef_id'])
                ? (int) $data['chef_id']
                : HrmEmployee::where('uuid', $data['chef_id'])->value('id');
        }

        $day = strtolower($data['day']);

        // Query existing meal record case-insensitively to avoid Postgres comparison issues
        $query = HrmWeeklyMeal::whereRaw('LOWER(day) = ?', [$day]);
        if ($currentOrg) {
            $query->where('organization_id', $currentOrg->id);
        }
        $meal = $query->first();

        $attributes = [
            'company_id'        => $currentOrg?->company_id,
            'organization_id'   => $currentOrg?->id,
            'day'               => $day,
            'lunch_item'        => $data['lunch_item'],
            'alternative_lunch' => $data['alternative_lunch'] ?? null,
            'expected_person'   => $data['expected_person'] ?? 0,
            'chef_id'           => $chefId,
            'chef_name'         => $data['chef_name'] ?? null,
            'is_active'         => $data['is_active'] ?? true,
            'updated_by'        => $userId,
        ];

        if ($meal) {
            $meal->update($attributes);
        } else {
            $attributes['created_by'] = $userId;
            $meal = HrmWeeklyMeal::create($attributes);
        }

        return $meal->load('chef');
    }
}
