<?php

namespace App\Actions\HrmHoliday;

use App\Models\HrmHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateHrmHolidayByDateAction
{
    /**
     * Execute bulk update or single creation of holidays by date.
     */
    public function execute(array $validated, Request $request): mixed
    {
        $date       = $validated['date'];
        $currentOrg = app('current_organization');

        return DB::transaction(function () use ($date, $request, $validated, $currentOrg) {
            // Case 1: Bulk array payload provided for date
            if ($request->has('holidays') && is_array($request->input('holidays'))) {
                $existingHolidays = HrmHoliday::where('date', $date)->get();
                $existingUuids    = $existingHolidays->pluck('uuid')->toArray();

                $inputHolidays = $validated['holidays'] ?? [];
                $inputUuids    = array_filter(array_column($inputHolidays, 'uuid'));

                // Delete holidays omitted from the array
                $uuidsToDelete = array_diff($existingUuids, $inputUuids);
                if (!empty($uuidsToDelete)) {
                    HrmHoliday::whereIn('uuid', $uuidsToDelete)->delete();
                }

                $results = [];
                foreach ($inputHolidays as $item) {
                    if (!empty($item['uuid'])) {
                        $holiday = HrmHoliday::where('uuid', $item['uuid'])->first();
                        if ($holiday) {
                            $holiday->update([
                                'name'      => $item['name'],
                                'is_active' => $item['is_active'] ?? $holiday->is_active,
                            ]);
                            $results[] = $holiday;
                        }
                    } else {
                        $createData = [
                            'name'      => $item['name'],
                            'date'      => $date,
                            'is_active' => $item['is_active'] ?? true,
                        ];
                        if ($currentOrg) {
                            $createData['organization_id'] = $currentOrg->id;
                            $createData['company_id']      = $currentOrg->company_id;
                        }
                        $results[] = HrmHoliday::create($createData);
                    }
                }

                return $results;
            }

            // Case 2: Single holiday payload at top-level with UUID
            if ($request->filled('uuid')) {
                $holiday = HrmHoliday::where('uuid', $request->uuid)->first();
                if ($holiday) {
                    $holiday->update(array_filter([
                        'name'      => $request->input('name', $holiday->name),
                        'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $holiday->is_active,
                    ]));
                    return $holiday;
                }
            }

            $match = [
                'date' => $date,
                'name' => $request->input('name'),
            ];
            if ($currentOrg) {
                $match['organization_id'] = $currentOrg->id;
            }

            return HrmHoliday::updateOrCreate(
                $match,
                [
                    'company_id' => $currentOrg ? $currentOrg->company_id : null,
                    'is_active'  => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
                ]
            );
        });
    }
}
