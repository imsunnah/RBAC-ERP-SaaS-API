<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DesignationRequest as MetadataRequest;
use App\Http\Resources\MetadataResource;
use App\Models\Religion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReligionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Religion::class);
        $religions = Religion::paginate($request->get('limit', 15));

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($religions)->response()->getData(true),
        ]);
    }

    public function store(MetadataRequest $request): JsonResponse
    {
        $this->authorize('create', Religion::class);
        $religion = Religion::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Religion created successfully.',
            'data' => new MetadataResource($religion),
        ], 201);
    }

    public function show(Religion $religion): JsonResponse
    {
        $this->authorize('view', $religion);

        return response()->json([
            'success' => true,
            'data' => new MetadataResource($religion),
        ]);
    }

    public function update(MetadataRequest $request, Religion $religion): JsonResponse
    {
        $this->authorize('update', $religion);
        $religion->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Religion updated successfully.',
            'data' => new MetadataResource($religion),
        ]);
    }

    public function destroy(Religion $religion): JsonResponse
    {
        $this->authorize('delete', $religion);
        $religion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Religion deleted successfully.',
        ]);
    }
}
