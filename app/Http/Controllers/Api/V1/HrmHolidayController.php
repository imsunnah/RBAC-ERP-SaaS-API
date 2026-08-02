<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmHoliday\StoreHrmHolidayRequest;
use App\Http\Requests\HrmHoliday\UpdateHrmHolidayRequest;
use App\Http\Requests\HrmHoliday\UpdateHrmHolidayByDateRequest;
use App\Models\HrmHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrmHolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HrmHoliday::orderBy('date', 'asc');

        if ($request->filled('year') && $request->filled('month')) {
            $year  = (int) $request->year;
            $month = (int) $request->month;
            $query->whereYear('date', $year)
                  ->whereMonth('date', $month);

            // For calendar view, return the full list of holidays in that month without pagination
            $holidays = $query->get();

            return response()->json([
                'success' => true,
                'data'    => $holidays,
            ]);
        }

        $limit = $request->get('limit', 15);
        $holidays = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => $holidays,
        ]);
    }

    public function store(StoreHrmHolidayRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $currentOrg = app('current_organization');
        $match = [
            'date' => $validated['date'],
            'name' => $validated['name'],
        ];
        if ($currentOrg) {
            $match['organization_id'] = $currentOrg->id;
        }

        $holiday = HrmHoliday::updateOrCreate(
            $match,
            [
                'company_id' => $currentOrg ? $currentOrg->company_id : null,
                'is_active'  => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Holiday information saved successfully.',
            'data'    => $holiday,
        ], 201);
    }

    public function show(HrmHoliday $hrmHoliday): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $hrmHoliday,
        ]);
    }

    public function update(UpdateHrmHolidayRequest $request, HrmHoliday $hrmHoliday): JsonResponse
    {
        $validated = $request->validated();

        $hrmHoliday->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Holiday updated successfully.',
            'data'    => $hrmHoliday,
        ]);
    }

    public function destroy(HrmHoliday $hrmHoliday): JsonResponse
    {
        $hrmHoliday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Holiday deleted successfully.',
        ]);
    }

    public function updateByDate(UpdateHrmHolidayByDateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $date = $validated['date'];
        $currentOrg = app('current_organization');

        $result = DB::transaction(function () use ($date, $request, $validated, $currentOrg) {
            // Case 1: Bulk array payload provided for date
            if ($request->has('holidays') && is_array($request->input('holidays'))) {
                $existingHolidays = HrmHoliday::where('date', $date)->get();
                $existingUuids = $existingHolidays->pluck('uuid')->toArray();

                $inputHolidays = $validated['holidays'] ?? [];
                $inputUuids = array_filter(array_column($inputHolidays, 'uuid'));

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

            // Case 2: Single holiday payload at top-level
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

        return response()->json([
            'success' => true,
            'message' => 'Holiday information updated/created successfully for ' . $date,
            'data'    => $result,
        ]);
    }
}
