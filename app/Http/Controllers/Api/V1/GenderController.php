<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DesignationRequest as MetadataRequest; // reusing validation logic
use App\Http\Resources\MetadataResource;
use App\Models\Gender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Gender::class);
        $genders = Gender::paginate($request->get('limit', 15));

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($genders)->response()->getData(true),
        ]);
    }

    public function store(MetadataRequest $request): JsonResponse
    {
        $this->authorize('create', Gender::class);
        $gender = Gender::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gender created successfully.',
            'data' => new MetadataResource($gender),
        ], 201);
    }

    public function show(Gender $gender): JsonResponse
    {
        $this->authorize('view', $gender);

        return response()->json([
            'success' => true,
            'data' => new MetadataResource($gender),
        ]);
    }

    public function update(MetadataRequest $request, Gender $gender): JsonResponse
    {
        $this->authorize('update', $gender);
        $gender->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gender updated successfully.',
            'data' => new MetadataResource($gender),
        ]);
    }

    public function destroy(Gender $gender): JsonResponse
    {
        $this->authorize('delete', $gender);
        $gender->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gender deleted successfully.',
        ]);
    }
}
