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
use App\Actions\HrmEmployeeMealSelection\GetHrmEmployeeMealOverviewAction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmEmployeeMealSelectionController extends Controller
{
    private const RELATIONS = ['employee', 'weeklyMeal', 'weeklyMeal.chef'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployeeMealSelection::class);

        $request->validate([
            'emp_uuid'   => ['nullable', 'uuid'],
            'date'       => ['nullable', 'date_format:d-m-Y'],
            'month'       => ['nullable', 'date_format:m-Y'],
            'start_date' => ['nullable', 'date_format:d-m-Y'],
            'end_date'   => ['nullable', 'date_format:d-m-Y', 'after_or_equal:start_date'],
            'per_page'   => ['nullable', 'integer', 'min:1'],
        ]);

        $query = HrmEmployeeMealSelection::with(self::RELATIONS);

        if ($request->filled('emp_uuid')) {
            $empUuid = $request->input('emp_uuid');
            
            if ($empUuid === 'true' || $empUuid === true || $request->boolean('emp_uuid')) {
                // Resolve authenticated user's employee record
                $user = $request->user();
                if ($user) {
                    $empId = $user->employee_id;

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

        $hasDateFilter = false;

        if ($request->filled('date')) {
            try {
                $query->where('date', \Carbon\Carbon::parse($request->date)->format('Y-m-d'));
                $hasDateFilter = true;
            } catch (\Exception $e) {}
        }

        if ($request->filled('start_date')) {
            try {
                $query->where('date', '>=', \Carbon\Carbon::parse($request->start_date)->format('Y-m-d'));
                $hasDateFilter = true;
            } catch (\Exception $e) {}
        }

        if ($request->filled('end_date')) {
            try {
                $query->where('date', '<=', \Carbon\Carbon::parse($request->end_date)->format('Y-m-d'));
                $hasDateFilter = true;
            } catch (\Exception $e) {}
        }
        
        if ($request->filled('month')) {
            try {
                $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $request->month);
                $query->whereYear('date', $monthDate->year)
                      ->whereMonth('date', $monthDate->month);
                $hasDateFilter = true;
            } catch (\Exception $e) {}
        }

        if (!$hasDateFilter) {
            $now = \Carbon\Carbon::now();
            $startOfWeek = $now->copy()->startOfWeek(\Carbon\Carbon::SATURDAY);
            $endOfWeek = $startOfWeek->copy()->addDays(5); // Thursday
            
            $query->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
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

    // For admin Pannel Dashboard

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployeeMealSelection::class);

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date'],
        ]);

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            try {
                $startDate = \Carbon\Carbon::parse($validated['start_date']);
                $endDate = \Carbon\Carbon::parse($validated['end_date']);
                
                // Limit range to prevent memory issues (max 90 days)
                if ($startDate->diffInDays($endDate) > 90) {
                    $endDate = $startDate->copy()->addDays(90);
                }
            } catch (\Exception $e) {
                $startDate = null;
            }
        }

        // Default to semi-monthly/fortnightly range logic if dates not provided or parsing failed
        if (empty($startDate) || empty($endDate)) {
            $today = now();
            if ($today->day <= 15) {
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->startOfMonth()->addDays(14); // 15th
            } else {
                $startDate = $today->copy()->startOfMonth()->addDays(15); // 16th
                $endDate = $today->copy()->endOfMonth(); // End of month
            }
        }

        // Generate consecutive dates
        $dates = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        $selections = HrmEmployeeMealSelection::whereIn('date', $dates)
            ->whereIn('selection', ['standard', 'alternative'])
            ->selectRaw('date, selected_item_name, count(*) as total')
            ->groupBy('date', 'selected_item_name')
            ->get();

        // Today's details
        $todayDate = now()->format('Y-m-d');
        $todaySelections = HrmEmployeeMealSelection::whereDate('date', $todayDate)
            ->whereIn('selection', ['standard', 'alternative'])
            ->selectRaw('selected_item_name, count(*) as total')
            ->groupBy('selected_item_name')
            ->get();

        $todayBreakdown = $todaySelections->map(fn($row) => [
            'meal_type' => $row->selected_item_name ?? '-',
            'count'     => (int) $row->total,
        ])->toArray();

        $todayTotal = (int) $todaySelections->sum('total');

        // History compilation
        $historyData = [];
        $totalQuantitySum = 0;

        // Group selections by date string
        $selectionsByDate = $selections->groupBy(fn($row) => \Carbon\Carbon::parse($row->date)->format('Y-m-d'));

        foreach ($dates as $dateStr) {
            $daySelections = $selectionsByDate->get($dateStr, collect());
            
            $dayTotal = (int) $daySelections->sum('total');
            $totalQuantitySum += $dayTotal;

            $dayBreakdown = $daySelections->map(fn($row) => [
                'meal_type' => $row->selected_item_name ?? '-',
                'count'     => (int) $row->total,
            ])->toArray();

            $historyData[] = [
                'date'           => $dateStr,
                'formatted_date' => \Carbon\Carbon::parse($dateStr)->format('d F'),
                'total_meals'    => $dayTotal,
                'breakdown'      => $dayBreakdown,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'today' => [
                    'date'      => $todayDate,
                    'total'     => $todayTotal,
                    'breakdown' => $todayBreakdown,
                ],
                'history' => [
                    'total_quantity' => $totalQuantitySum,
                    'days'           => $historyData,
                ]
            ]
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

    // User Meal Ovew View

    public function mealOverview(
        Request $request,
        GetHrmEmployeeMealOverviewAction $action
    ): JsonResponse {
        $this->authorize('viewAny', HrmEmployeeMealSelection::class);

        $user = $request->user();

        // Validate optional inputs
        $validated = $request->validate([
            'start_date' => ['nullable', 'string'],
            'end_date'   => ['nullable', 'string'],
            'emp_uuid'   => ['nullable', 'uuid'],
        ]);

        $stats = $action->execute($user, $validated);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ], 200);
    }

}
