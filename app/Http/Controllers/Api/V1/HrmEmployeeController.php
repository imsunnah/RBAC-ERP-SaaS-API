<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmEmployee\StoreHrmEmployeeRequest;
use App\Http\Requests\HrmEmployee\UpdateHrmEmployeeRequest;
use App\Models\HrmEmployee;
use Illuminate\Http\JsonResponse;

class HrmEmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     */
    public function index(): JsonResponse
    {
        // By default, CompanyScope will filter this to the current company
        $employees = HrmEmployee::latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $employees,
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
            'data' => $employee,
        ], 201);
    }

    /**
     * Display the specified employee.
     */
    public function show(HrmEmployee $hrmEmployee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $hrmEmployee,
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
            'data' => $hrmEmployee->fresh(),
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
