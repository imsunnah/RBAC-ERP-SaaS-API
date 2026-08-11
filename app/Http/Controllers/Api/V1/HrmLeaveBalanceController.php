<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmLeaveBalance\StoreHrmLeaveBalanceRequest;
use App\Http\Resources\HrmLeaveBalanceResource;
use App\Models\HrmLeaveBalance;
use App\Models\HrmEmployee;
use App\Models\HrmLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Actions\HrmLeaveBalance\SaveHrmLeaveBalanceAction;

class HrmLeaveBalanceController extends Controller
{
    private const RELATIONS = ['employee', 'leaveType'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmLeaveBalance::class);

        $query = HrmLeaveBalance::with(self::RELATIONS);

        if ($request->filled('employee')) {
            $empId = HrmEmployee::where('uuid', $request->employee)->value('id');
            if ($empId) {
                $query->where('hrm_employee_id', $empId);
            }
        }

        if ($request->filled('leave_type')) {
            $typeId = HrmLookup::where('uuid', $request->leave_type)->value('id');
            if ($typeId) {
                $query->where('leave_type_id', $typeId);
            }
        }

        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }

        $balances = $query->get();

        return response()->json([
            'success' => true,
            'data'    => HrmLeaveBalanceResource::collection($balances),
        ]);
    }

    public function store(
        StoreHrmLeaveBalanceRequest $request,
        SaveHrmLeaveBalanceAction $action
    ): JsonResponse {
        $this->authorize('create', HrmLeaveBalance::class);

        $result = $action->execute($request->validated());

        $responseData = is_array($result)
            ? HrmLeaveBalanceResource::collection(collect($result)->load(self::RELATIONS))
            : new HrmLeaveBalanceResource($result->load(self::RELATIONS));

        return response()->json([
            'success' => true,
            'message' => 'Leave balance saved successfully.',
            'data'    => $responseData,
        ], 201);
    }
}
