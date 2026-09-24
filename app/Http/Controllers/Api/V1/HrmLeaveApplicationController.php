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

        $validated = $request->validate([
            'date'          => ['nullable', 'date'],
            'search'        => ['nullable', 'string', 'max:100'],
            'department'    => ['nullable', 'uuid'],
            'per_page'      => ['nullable', 'integer', 'min:1'],
            'limit'         => ['nullable', 'integer', 'min:1'],
            'employee_id'   => ['nullable', 'uuid'],
            'status'        => ['nullable', 'string'],
            'leave_type_id' => ['nullable', 'uuid'],
        ]);

        $query = HrmLeaveApplication::with(self::RELATIONS)->orderBy('created_at', 'desc');

        $user = $request->user();
        if (!$user || !$user->is_super_admin) {
            $employeeId = $user?->employee_id ?: (HrmEmployee::where('office_email', $user?->email)->value('id') ?: ($user?->id ?: 0));
            $query->where('hrm_employee_id', $employeeId);
        } elseif (!empty($validated['employee_id'])) {
            $empId = HrmEmployee::where('uuid', $validated['employee_id'])->value('id');
            if ($empId) {
                $query->where('hrm_employee_id', $empId);
            }
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['leave_type_id'])) {
            $typeId = HrmLookup::where('uuid', $validated['leave_type_id'])->value('id');
            if ($typeId) {
                $query->where('leave_type_id', $typeId);
            }
        }

        if (!empty($validated['department'])) {
            $deptId = HrmLookup::where('uuid', $validated['department'])->value('id');
            if ($deptId) {
                $query->whereHas('employee', function ($q) use ($deptId) {
                    $q->where('department_id', (string) $deptId);
                });
            }
        }

        if (!empty($validated['search'])) {
            $searchTerm = $validated['search'];

            // Pre-fetch matching lookup IDs to prevent Postgres type mismatches
            $matchingDeptIds = HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'department'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();

            $matchingDesigIds = HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'designation'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();

            $matchingLeaveTypeIds = HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->whereIn('slug', ['leave-type', 'leave_type', 'leave']))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->toArray();

            $query->where(function ($q) use ($searchTerm, $matchingDeptIds, $matchingDesigIds, $matchingLeaveTypeIds) {
                $q->where('reason', 'like', "%{$searchTerm}%")
                  ->orWhere('rejection_reason', 'like', "%{$searchTerm}%")
                  ->orWhereHas('employee', function ($empQ) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                      $empQ->where('full_name', 'like', "%{$searchTerm}%")
                           ->orWhere('user_employee_id', 'like', "%{$searchTerm}%");
                      if (!empty($matchingDeptIds)) {
                          $empQ->orWhereIn('department_id', $matchingDeptIds);
                      }
                      if (!empty($matchingDesigIds)) {
                          $empQ->orWhereIn('designation_id', $matchingDesigIds);
                      }
                  });

                if (!empty($matchingLeaveTypeIds)) {
                    $q->orWhereIn('leave_type_id', $matchingLeaveTypeIds);
                }
            });
        }

        if (!empty($validated['date'])) {
            try {
                $searchDate = \Carbon\Carbon::parse($validated['date'])->format('Y-m-d');
                $query->where('from_date', '<=', $searchDate)
                      ->where('to_date', '>=', $searchDate);
            } catch (\Exception $e) {}
        }



        $limit = $validated['limit'] ?? $validated['per_page'] ?? 15;
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
