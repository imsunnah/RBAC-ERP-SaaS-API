<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrmLeavePolicy\CreateHrmLeavePolicyAction;
use App\Actions\HrmLeavePolicy\DeleteHrmLeavePolicyAction;
use App\Actions\HrmLeavePolicy\UpdateHrmLeavePolicyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrmLeavePolicy\StoreHrmLeavePolicyRequest;
use App\Http\Requests\HrmLeavePolicy\UpdateHrmLeavePolicyRequest;
use App\Http\Resources\HrmLeavePolicyResource;
use App\Models\HrmLeavePolicy;
use App\Models\HrmLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmLeavePolicyController extends Controller
{
    private const RELATIONS = ['leaveType', 'employeeType', 'updater'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'leave_type' => ['nullable', 'uuid', 'exists:hrm_lookups,uuid'],
            'employee_type' => ['nullable', 'uuid', 'exists:hrm_lookups,uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = HrmLeavePolicy::with(self::RELATIONS)->latest('updated_at');

        if ($request->filled('leave_type')) {
            $query->where('leave_type_id', HrmLookup::where('uuid', $request->leave_type)->value('id'));
        }

        if ($request->filled('employee_type')) {
            $query->where('employee_type_id', HrmLookup::where('uuid', $request->employee_type)->value('id'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($policyQuery) use ($search) {
                $policyQuery->whereHas('leaveType', fn ($lookupQuery) => $lookupQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('employeeType', fn ($lookupQuery) => $lookupQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $policies = $query->paginate((int) $request->input('limit', 15));

        return response()->json([
            'success' => true,
            'data' => HrmLeavePolicyResource::collection($policies)->response()->getData(true),
        ]);
    }

    public function store(
        StoreHrmLeavePolicyRequest $request,
        CreateHrmLeavePolicyAction $action
    ): JsonResponse {
        $policy = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Leave policy created successfully.',
            'data' => new HrmLeavePolicyResource($policy),
        ], 201);
    }

    public function update(
        UpdateHrmLeavePolicyRequest $request,
        HrmLeavePolicy $hrmLeavePolicy,
        UpdateHrmLeavePolicyAction $action
    ): JsonResponse {
        $policy = $action->execute($hrmLeavePolicy, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Leave policy updated successfully.',
            'data' => new HrmLeavePolicyResource($policy),
        ]);
    }

    public function destroy(
        HrmLeavePolicy $hrmLeavePolicy,
        DeleteHrmLeavePolicyAction $action
    ): JsonResponse {
        $action->execute($hrmLeavePolicy);

        return response()->json([
            'success' => true,
            'message' => 'Leave policy deleted successfully.',
        ]);
    }
}
