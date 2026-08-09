<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrmEmployee\CreateHrmEmployeeAction;
use App\Actions\HrmEmployee\DeleteHrmEmployeeAction;
use App\Actions\HrmEmployee\GetEmployeeDashboardAction;
use App\Actions\HrmEmployee\UpdateHrmEmployeeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrmEmployee\StoreHrmEmployeeRequest;
use App\Http\Requests\HrmEmployee\UpdateHrmEmployeeRequest;
use App\Http\Resources\HrmEmployeeIndexResource;
use App\Http\Resources\HrmEmployeeResource;
use App\Models\HrmEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmEmployeeController extends Controller
{
    /** Eager-load relations shared across index / store / update / show. */
    private const EMPLOYEE_RELATIONS = [
        'department',
        'designation',
        'employeeType',
        'gender',
        'bloodGroup',
        'religion',
        'maritalStatus',
        'reportingManager',
        'education',
        'experience',
        'family',
        'family.gender',
        'family.relationship',
        'family.maritalStatus',
        'family.bloodGroup',
        'workShift',
        'emergencyContacts',
        'emergencyContacts.relationship',
        'documents',
    ];

    /** Display a listing of the employees. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployee::class);

        $query = HrmEmployee::with(self::EMPLOYEE_RELATIONS);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('user_employee_id', 'like', "%{$search}%")
                    ->orWhere('office_email', 'like', "%{$search}%")
                    ->orWhere('office_mobile_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('name')) {
            $query->where('full_name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('email')) {
            $query->where('office_email', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('status')) {
            $status = filter_var($request->status, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $status);
        }

        $query->orderBy('user_employee_id', 'asc');

        $limit = min((int) $request->input('limit', $request->input('per_page', 15)), 50000);

        if ($limit > 1000) {
            ini_set('memory_limit', '512M');
            set_time_limit(120);
        }

        $employees = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => HrmEmployeeIndexResource::collection($employees)->response()->getData(true),
        ]);
    }

    /** Single unified Employee Management Dashboard GET API. */
    public function dashboard(Request $request, GetEmployeeDashboardAction $action): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployee::class);

        $data = $action->execute();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /** Store a newly created employee. */
    public function store(
        StoreHrmEmployeeRequest $request,
        CreateHrmEmployeeAction $action
    ): JsonResponse {
        $this->authorize('create', HrmEmployee::class);

        $employee = $action->execute(
            $request->validated(),
            $request,
            self::EMPLOYEE_RELATIONS
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data'    => new HrmEmployeeResource($employee),
        ], 201);
    }

    /** Display the specified employee. */
    public function show(HrmEmployee $hrmEmployee): JsonResponse
    {
        $this->authorize('view', $hrmEmployee);

        $hrmEmployee->load(self::EMPLOYEE_RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new HrmEmployeeResource($hrmEmployee),
        ]);
    }

    /** Update the specified employee. */
    public function update(
        UpdateHrmEmployeeRequest $request,
        HrmEmployee $hrmEmployee,
        UpdateHrmEmployeeAction $action
    ): JsonResponse {
        $this->authorize('update', $hrmEmployee);

        $updatedEmployee = $action->execute(
            $hrmEmployee,
            $request->validated(),
            $request,
            self::EMPLOYEE_RELATIONS
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data'    => new HrmEmployeeResource($updatedEmployee),
        ]);
    }

    /** Remove the specified employee. */
    public function destroy(
        HrmEmployee $hrmEmployee,
        DeleteHrmEmployeeAction $action
    ): JsonResponse {
        $this->authorize('delete', $hrmEmployee);

        $action->execute($hrmEmployee);

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }
}
