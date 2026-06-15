<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HrmAttendanceResource;
use App\Models\HrmAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmAttendanceController extends Controller
{
    /**
     * Display a listing of stored attendance records.
     */
    public function index(Request $request): JsonResponse
    {
        $query = HrmAttendance::with('employee')
            ->latest('punch_time');

        if ($request->has('biometric_id')) {
            $query->where('biometric_id', $request->biometric_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('punch_time', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('punch_time', '<=', $request->end_date);
        }

        $attendances = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => HrmAttendanceResource::collection($attendances)->response()->getData(true),
        ]);
    }
}
