<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DesignationRequest;
use App\Http\Resources\DesignationResource;
use App\Models\Company;
use App\Models\Designation;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Designation::class);
        $designations = Designation::paginate($request->get('limit', 15));

        return response()->json([
            'success' => true,
            'data' => DesignationResource::collection($designations)->response()->getData(true),
        ]);
    }

    public function store(DesignationRequest $request): JsonResponse
    {
        $this->authorize('create', Designation::class);

        $data = $request->validated();

        $data['organization_id'] = Organization::where('uuid', $data['organization_id'])->value('id');
        $data['company_id'] = Company::where('uuid', $data['company_id'])->value('id');

        $designation = Designation::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Designation created successfully.',
            'data' => new DesignationResource($designation),
        ], 201);
    }

    public function show(Designation $designation): JsonResponse
    {
        $this->authorize('view', $designation);

        return response()->json([
            'success' => true,
            'data' => new DesignationResource($designation),
        ]);
    }

    public function update(DesignationRequest $request, Designation $designation): JsonResponse
    {
        $this->authorize('update', $designation);
        $data = $request->validated();

        // Convert UUID → ID
        if (isset($data['organization_id'])) {
            $data['organization_id'] = Organization::where('uuid', $data['organization_id'])->value('id');
        }

        if (isset($data['company_id'])) {
            $data['company_id'] = Company::where('uuid', $data['company_id'])->value('id');
        }

        $designation->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Designation updated successfully.',
            'data' => new DesignationResource($designation->fresh()),
        ]);
    }

    public function destroy(Designation $designation): JsonResponse
    {
        $this->authorize('delete', $designation);
        $designation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Designation deleted successfully.',
        ]);
    }
}
