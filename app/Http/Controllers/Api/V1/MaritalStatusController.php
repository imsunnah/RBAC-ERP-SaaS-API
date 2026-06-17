<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DesignationRequest as MetadataRequest;
use App\Http\Resources\MetadataResource;
use App\Models\MaritalStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaritalStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaritalStatus::class);
        $statuses = MaritalStatus::paginate($request->get('limit', 15));

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($statuses)->response()->getData(true),
        ]);
    }

    public function store(MetadataRequest $request): JsonResponse
    {
        $this->authorize('create', MaritalStatus::class);
        $status = MaritalStatus::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Marital status created successfully.',
            'data' => new MetadataResource($status),
        ], 201);
    }

    public function show(MaritalStatus $maritalStatus): JsonResponse
    {
        $this->authorize('view', $maritalStatus);

        return response()->json([
            'success' => true,
            'data' => new MetadataResource($maritalStatus),
        ]);
    }

    public function update(MetadataRequest $request, MaritalStatus $maritalStatus): JsonResponse
    {
        $this->authorize('update', $maritalStatus);
        $maritalStatus->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Marital status updated successfully.',
            'data' => new MetadataResource($maritalStatus),
        ]);
    }

    public function destroy(MaritalStatus $maritalStatus): JsonResponse
    {
        $this->authorize('delete', $maritalStatus);
        $maritalStatus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Marital status deleted successfully.',
        ]);
    }
}
