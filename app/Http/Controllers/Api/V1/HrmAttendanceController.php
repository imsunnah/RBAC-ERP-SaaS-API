<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HrmAttendanceResource;
use App\Models\HrmAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmAttendanceController extends Controller
{
    /** Eager-load relations shared across index / store / update / show. */
    private const RELATIONS = [
        'employee.department',
        'employee.designation',
        'shift',
    ];

    /**
     * Display a listing of stored attendance records.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmAttendance::class);

        $request->validate([
            'date'       => ['nullable', 'date'],
            'search'     => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'uuid'],
            'per_page'   => ['nullable', 'integer'],
        ]);

        $query = HrmAttendance::with(self::RELATIONS)->latest('date');

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('department')) {
            $deptId = \App\Models\HrmLookup::where('uuid', $request->department)->value('id');
            if ($deptId) {
                $query->whereHas('employee', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            
            // Pre-fetch matching department and designation IDs to avoid Postgres type mismatch on varchar/bigint joins
            $matchingDeptIds = \App\Models\HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'department'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
                
            $matchingDesigIds = \App\Models\HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'designation'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->toArray();

            $query->where(function ($q) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                $q->where('status', 'like', "%{$searchTerm}%")
                  ->orWhereHas('shift', function ($shiftQ) use ($searchTerm) {
                      $shiftQ->where('name', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('employee', function ($empQ) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                      $empQ->where('full_name', 'like', "%{$searchTerm}%")
                           ->orWhere('user_employee_id', 'like', "%{$searchTerm}%");
                           
                      if (!empty($matchingDesigIds)) {
                          $empQ->orWhereIn('designation_id', $matchingDesigIds);
                      }
                      if (!empty($matchingDeptIds)) {
                          $empQ->orWhereIn('department_id', $matchingDeptIds);
                      }
                  });
            });
        }

        $attendances = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => HrmAttendanceResource::collection($attendances)->response()->getData(true),
        ]);
    }
}
