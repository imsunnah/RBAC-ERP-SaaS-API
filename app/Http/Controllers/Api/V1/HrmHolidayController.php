<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrmHoliday\GetHrmHolidayCalendarAction;
use App\Actions\HrmHoliday\UpdateHrmHolidayByDateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrmHoliday\StoreHrmHolidayRequest;
use App\Http\Requests\HrmHoliday\UpdateHrmHolidayByDateRequest;
use App\Http\Requests\HrmHoliday\UpdateHrmHolidayRequest;
use App\Models\HrmHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmHolidayController extends Controller
{
    /** Display a listing of holidays. */
    public function index(Request $request): JsonResponse
    {
        $query = HrmHoliday::orderBy('date', 'asc');

        if ($request->filled('year') && $request->filled('month')) {
            $query->whereYear('date', (int) $request->year)
                  ->whereMonth('date', (int) $request->month);

            return response()->json([
                'success' => true,
                'data'    => $query->get(),
            ]);
        }

        $limit = $request->get('limit', 15);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($limit),
        ]);
    }

    /** Holiday calendar for a given year/month. */
    public function calendar(Request $request, GetHrmHolidayCalendarAction $action): JsonResponse
    {
        $request->validate([
            'year'     => 'required|integer|min:2000|max:2100',
            'month'    => 'required|integer|min:1|max:12',
            'shift_id' => 'nullable',
        ]);

        $result = $action->execute($request);

        return response()->json([
            'success'      => true,
            'year'         => $result['year'],
            'month'        => $result['month'],
            'work_shift'   => $result['work_shift'],
            'weekend_days' => $result['weekend_days'],
            'data'         => $result['calendar'],
        ]);
    }

    /** Store a new holiday record. */
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

    /** Display a single holiday. */
    public function show(HrmHoliday $hrmHoliday): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $hrmHoliday,
        ]);
    }

    /** Update a holiday record. */
    public function update(UpdateHrmHolidayRequest $request, HrmHoliday $hrmHoliday): JsonResponse
    {
        $hrmHoliday->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Holiday updated successfully.',
            'data'    => $hrmHoliday,
        ]);
    }

    /** Delete a holiday record. */
    public function destroy(HrmHoliday $hrmHoliday): JsonResponse
    {
        $hrmHoliday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Holiday deleted successfully.',
        ]);
    }

    /** Update or bulk update holidays for a given date. */
    public function updateByDate(
        UpdateHrmHolidayByDateRequest $request,
        UpdateHrmHolidayByDateAction $action
    ): JsonResponse {
        $validated = $request->validated();
        $result = $action->execute($validated, $request);

        return response()->json([
            'success' => true,
            'message' => 'Holiday information updated/created successfully for ' . $validated['date'],
            'data'    => $result,
        ]);
    }
}
