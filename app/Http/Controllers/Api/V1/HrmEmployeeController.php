<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmEmployee\StoreHrmEmployeeRequest;
use App\Http\Requests\HrmEmployee\UpdateHrmEmployeeRequest;
use App\Models\HrmEmployee;
use App\Http\Resources\HrmEmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmEmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     */
    public function index(Request $request): JsonResponse
    {
        $employees = HrmEmployee::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('user_employee_id', 'ilike', "%{$search}%")
                        ->orWhere('office_email', 'ilike', "%{$search}%")
                        ->orWhere('office_mobile_no', 'ilike', "%{$search}%");
                });
            })
            ->when($request->department_id, fn($q, $id) => $q->where('department_id', $id))
            ->when($request->designation_id, fn($q, $id) => $q->where('designation_id', $id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('user_employee_id', 'asc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => HrmEmployeeResource::collection($employees)->response()->getData(true),
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(StoreHrmEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $employee = HrmEmployee::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => new HrmEmployeeResource($employee),
        ], 201);
    }

    /**
     * Display the specified employee.
     */
    public function show(HrmEmployee $hrmEmployee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new HrmEmployeeResource($hrmEmployee),
        ]);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(UpdateHrmEmployeeRequest $request, HrmEmployee $hrmEmployee): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = auth()->id();

        $hrmEmployee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => new HrmEmployeeResource($hrmEmployee->fresh()),
        ]);
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(HrmEmployee $hrmEmployee): JsonResponse
    {
        $hrmEmployee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }
}
