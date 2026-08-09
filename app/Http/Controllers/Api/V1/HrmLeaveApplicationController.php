<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrmLeaveApplication\CreateHrmLeaveApplicationAction;
use App\Actions\HrmLeaveApplication\DeleteHrmLeaveApplicationAction;
use App\Actions\HrmLeaveApplication\UpdateHrmLeaveApplicationAction;
use App\Actions\HrmLeaveApplication\UpdateHrmLeaveApplicationStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrmLeaveApplication\StoreHrmLeaveApplicationRequest;
use App\Http\Requests\HrmLeaveApplication\UpdateHrmLeaveApplicationRequest;
use App\Http\Resources\HrmLeaveApplicationResource;
use App\Models\HrmEmployee;
use App\Models\HrmLeaveApplication;
use App\Models\HrmLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmLeaveApplicationController extends Controller
{
    /** Eager-load relations for the leave application. */
    private const RELATIONS = [
        'employee',
        'leaveType',
        'approvedByUser',
        'documents',
    ];

    /** List leave applications with filtering. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmLeaveApplication::class);

        $query = HrmLeaveApplication::with(self::RELATIONS)->orderBy('created_at', 'desc');

        if ($request->filled('employee_id')) {
            $empId = HrmEmployee::where('uuid', $request->employee_id)->value('id');
            if ($empId) {
                $query->where('hrm_employee_id', $empId);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type_id')) {
            $typeId = HrmLookup::where('uuid', $request->leave_type_id)->value('id');
            if ($typeId) {
                $query->where('leave_type_id', $typeId);
            }
        }

        if ($request->filled('from_date')) {
            $query->where('from_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('to_date', '<=', $request->to_date);
        }

        $limit = $request->get('limit', 15);
        $leaves = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => HrmLeaveApplicationResource::collection($leaves)->response()->getData(true),
        ]);
    }

    /** Store a new leave application. */
    public function store(
        StoreHrmLeaveApplicationRequest $request,
        CreateHrmLeaveApplicationAction $action
    ): JsonResponse {
        $this->authorize('create', HrmLeaveApplication::class);

        $leave = $action->execute($request->validated(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted successfully.',
            'data'    => new HrmLeaveApplicationResource($leave),
        ], 201);
    }

    /** Show a single leave application. */
    public function show(HrmLeaveApplication $hrmLeaveApplication): JsonResponse
    {
        $this->authorize('view', $hrmLeaveApplication);

        $hrmLeaveApplication->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new HrmLeaveApplicationResource($hrmLeaveApplication),
        ]);
    }

    /** Update a leave application. */
    public function update(
        UpdateHrmLeaveApplicationRequest $request,
        HrmLeaveApplication $hrmLeaveApplication,
        UpdateHrmLeaveApplicationAction $action
    ): JsonResponse {
        $this->authorize('update', $hrmLeaveApplication);

        $leave = $action->execute($hrmLeaveApplication, $request->validated(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Leave application updated successfully.',
            'data'    => new HrmLeaveApplicationResource($leave),
        ]);
    }

    /** Delete a leave application. */
    public function destroy(
        HrmLeaveApplication $hrmLeaveApplication,
        DeleteHrmLeaveApplicationAction $action
    ): JsonResponse {
        $this->authorize('delete', $hrmLeaveApplication);

        $action->execute($hrmLeaveApplication);

        return response()->json([
            'success' => true,
            'message' => 'Leave application deleted successfully.',
        ]);
    }

    /** Approve, reject, or cancel a leave application. */
    public function updateStatus(
        Request $request,
        HrmLeaveApplication $hrmLeaveApplication,
        UpdateHrmLeaveApplicationStatusAction $action
    ): JsonResponse {
        $this->authorize('approve', $hrmLeaveApplication);

        $request->validate([
            'status'           => 'required|string|in:approved,rejected,cancelled',
            'rejection_reason' => 'nullable|string|max:2000',
        ]);

        $leave = $action->execute(
            $hrmLeaveApplication,
            $request->input('status'),
            $request->input('rejection_reason')
        );

        $statusLabel = ucfirst($leave->status);

        return response()->json([
            'success' => true,
            'message' => "Leave application {$statusLabel} successfully.",
            'data'    => new HrmLeaveApplicationResource($leave),
        ]);
    }
}
