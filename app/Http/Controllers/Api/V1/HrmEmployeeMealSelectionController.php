<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmEmployeeMealSelection\StoreHrmEmployeeMealSelectionRequest;
use App\Http\Resources\HrmEmployeeMealSelectionResource;
use App\Models\HrmEmployeeMealSelection;
use App\Models\HrmEmployee;
use App\Models\HrmWeeklyMeal;
use App\Actions\HrmEmployeeMealSelection\SaveHrmEmployeeMealSelectionAction;
use App\Actions\HrmEmployeeMealSelection\GetHrmEmployeeMealListAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmEmployeeMealSelectionController extends Controller
{
    private const RELATIONS = ['employee', 'weeklyMeal'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployeeMealSelection::class);

        $query = HrmEmployeeMealSelection::with(self::RELATIONS);

        if ($request->filled('emp_uuid')) {
            $empUuid = $request->input('emp_uuid');
            
            if ($empUuid === 'true' || $empUuid === true || $request->boolean('emp_uuid')) {
                // Resolve authenticated user's employee record
                $user = $request->user();
                if ($user) {
                    $empId = HrmEmployee::where(function ($q) use ($user) {
                        $email = strtolower($user->email);
                        $q->whereRaw('LOWER(office_email) = ?', [$email])
                          ->orWhereRaw('LOWER(personal_email) = ?', [$email]);
                    })->value('id');

                    if ($empId) {
                        $query->where('hrm_employee_id', $empId);
                    } else {
                        $query->where('hrm_employee_id', 0);
                    }
                }
            } else {
                // Resolve the specified employee UUID
                $empId = HrmEmployee::where('uuid', $empUuid)->value('id');
                if ($empId) {
                    $query->where('hrm_employee_id', $empId);
                } else {
                    $query->where('hrm_employee_id', 0);
                }
            }
        } elseif ($request->filled('employee')) {
            $empId = HrmEmployee::where('uuid', $request->employee)->value('id');
            if ($empId) {
                $query->where('hrm_employee_id', $empId);
            }
        }

        if ($request->filled('date')) {
            try {
                $query->where('date', \Carbon\Carbon::parse($request->date)->format('Y-m-d'));
            } catch (\Exception $e) {}
        }

        if ($request->filled('start_date')) {
            try {
                $query->where('date', '>=', \Carbon\Carbon::parse($request->start_date)->format('Y-m-d'));
            } catch (\Exception $e) {}
        }

        if ($request->filled('end_date')) {
            try {
                $query->where('date', '<=', \Carbon\Carbon::parse($request->end_date)->format('Y-m-d'));
            } catch (\Exception $e) {}
        }

        $selections = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => HrmEmployeeMealSelectionResource::collection($selections)->response()->getData(true),
        ]);
    }

    public function todayMealList(Request $request, GetHrmEmployeeMealListAction $action): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployeeMealSelection::class);

        $validated = $request->validate([
            'date'       => ['nullable', 'date'],
            'department' => ['nullable', 'uuid'],
            'search'     => ['nullable', 'string', 'max:100'],
            'per_page'   => ['nullable', 'integer', 'min:1'],
        ]);

        $data = $action->execute($validated);

        return response()->json([
            'success' => true,
            ...$data,
        ]);
    }

    public function store(
        StoreHrmEmployeeMealSelectionRequest $request,
        SaveHrmEmployeeMealSelectionAction $action
    ): JsonResponse {
        $this->authorize('create', HrmEmployeeMealSelection::class);

        $result = $action->execute($request->validated());

        $responseData = is_array($result)
            ? HrmEmployeeMealSelectionResource::collection(collect($result)->load(self::RELATIONS))
            : new HrmEmployeeMealSelectionResource($result->load(self::RELATIONS));

        return response()->json([
            'success' => true,
            'message' => 'Meal selections saved successfully.',
            'data'    => $responseData,
        ], 201);
    }
}
