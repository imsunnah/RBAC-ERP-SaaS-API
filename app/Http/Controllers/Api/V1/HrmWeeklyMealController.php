<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrmWeeklyMeal\CreateHrmWeeklyMealAction;
use App\Actions\HrmWeeklyMeal\DeleteHrmWeeklyMealAction;
use App\Actions\HrmWeeklyMeal\UpdateHrmWeeklyMealAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrmWeeklyMeal\StoreHrmWeeklyMealRequest;
use App\Http\Requests\HrmWeeklyMeal\UpdateHrmWeeklyMealRequest;
use App\Http\Resources\HrmWeeklyMealResource;
use App\Models\HrmWeeklyMeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmWeeklyMealController extends Controller
{
    /** Day sort order map for natural weekly ordering starting from Saturday. */
    private const DAY_ORDER = [
        'saturday'  => 1,
        'sunday'    => 2,
        'monday'    => 3,
        'tuesday'   => 4,
        'wednesday' => 5,
        'thursday'  => 6,
        'friday'    => 7,
    ];

    /** List all weekly meals. */
    public function index(Request $request): JsonResponse
    {
        $query = HrmWeeklyMeal::with('chef');

        if ($request->filled('day')) {
            $query->where('day', strtolower($request->day));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('lunch_item', 'like', "%{$search}%")
                  ->orWhere('alternative_lunch', 'like', "%{$search}%")
                  ->orWhere('chef_name', 'like', "%{$search}%");
            });
        }

        $meals = $query->get()->sortBy(fn ($m) => self::DAY_ORDER[strtolower($m->day)] ?? 99)->values();

        return response()->json([
            'success' => true,
            'data'    => HrmWeeklyMealResource::collection($meals),
        ]);
    }

    /** Store a new weekly meal setup (single or bulk). */
    public function store(
        StoreHrmWeeklyMealRequest $request,
        CreateHrmWeeklyMealAction $action
    ): JsonResponse {
        $result = $action->execute($request->validated());

        $responseData = is_array($result)
            ? HrmWeeklyMealResource::collection(collect($result))
            : new HrmWeeklyMealResource($result);

        return response()->json([
            'success' => true,
            'message' => 'Weekly meal setup saved successfully.',
            'data'    => $responseData,
        ], 201);
    }

    /** Display a single weekly meal entry. */
    public function show(HrmWeeklyMeal $hrmWeeklyMeal): JsonResponse
    {
        $hrmWeeklyMeal->load('chef');

        return response()->json([
            'success' => true,
            'data'    => new HrmWeeklyMealResource($hrmWeeklyMeal),
        ]);
    }

    /** Update a weekly meal entry. */
    public function update(
        UpdateHrmWeeklyMealRequest $request,
        HrmWeeklyMeal $hrmWeeklyMeal,
        UpdateHrmWeeklyMealAction $action
    ): JsonResponse {
        $meal = $action->execute($hrmWeeklyMeal, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Weekly meal setup updated successfully.',
            'data'    => new HrmWeeklyMealResource($meal),
        ]);
    }

    /** Delete a weekly meal entry. */
    public function destroy(
        HrmWeeklyMeal $hrmWeeklyMeal,
        DeleteHrmWeeklyMealAction $action
    ): JsonResponse {
        $action->execute($hrmWeeklyMeal);

        return response()->json([
            'success' => true,
            'message' => 'Weekly meal setup deleted successfully.',
        ]);
    }
}
