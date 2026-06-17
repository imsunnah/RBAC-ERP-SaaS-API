<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Company;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);
        $departments = Department::paginate($request->get('limit', 15));

        return response()->json([
            'success' => true,
            'data' => DepartmentResource::collection($departments)->response()->getData(true),
        ]);
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $data = $request->validated();

        $data['organization_id'] = Organization::where('uuid', $data['organization_id'])->value('id');
        $data['company_id'] = Company::where('uuid', $data['company_id'])->value('id');

        $department = Department::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully.',
            'data' => new DepartmentResource($department),
        ], 201);
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return response()->json([
            'success' => true,
            'data' => new DepartmentResource($department),
        ]);
    }

    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $data = $request->validated();

        // Convert UUID → ID
        if (isset($data['organization_id'])) {
            $data['organization_id'] = Organization::where('uuid', $data['organization_id'])->value('id');
        }

        if (isset($data['company_id'])) {
            $data['company_id'] = Company::where('uuid', $data['company_id'])->value('id');
        }

        $department->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => new DepartmentResource($department->fresh()),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);
        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }
}
