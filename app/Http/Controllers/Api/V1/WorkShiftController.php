<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkShift\StoreWorkShiftRequest;
use App\Http\Requests\WorkShift\UpdateWorkShiftRequest;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);
        $shifts = WorkShift::orderBy('shift_id', 'asc')->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => $shifts,
        ]);
    }

    public function store(StoreWorkShiftRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $currentOrg = app('current_organization');
        if ($currentOrg) {
            $validated['organization_id'] = $currentOrg->id;
            $validated['company_id']      = $currentOrg->company_id;
        }

        $shift = WorkShift::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Work shift created successfully.',
            'data'    => $shift,
        ], 201);
    }

    public function show(WorkShift $workShift): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $workShift,
        ]);
    }

    public function update(UpdateWorkShiftRequest $request, WorkShift $workShift): JsonResponse
    {
        $validated = $request->validated();

        $workShift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Work shift updated successfully.',
            'data'    => $workShift,
        ]);
    }

    public function destroy(WorkShift $workShift): JsonResponse
    {
        $workShift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Work shift deleted successfully.',
        ]);
    }

    public function fullIndex(Request $request): JsonResponse
    {
        $shifts = WorkShift::orderBy('shift_id', 'asc')
            ->where('is_active', true)
            ->get()
            ->transform(function (WorkShift $shift): WorkShift {
                if ($shift->start_time) {
                    $shift->start_time = Carbon::parse($shift->start_time)->format('g:i A');
                }

                if ($shift->end_time) {
                    $shift->end_time = Carbon::parse($shift->end_time)->format('g:i A');
                }

                return $shift;
            });

        return response()->json([
            'success' => true,
            'data'    => $shifts,
        ]);
    }
}
