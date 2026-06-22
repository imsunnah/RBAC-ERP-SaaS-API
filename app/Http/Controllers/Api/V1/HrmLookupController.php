<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmLookupGroupRequest;
use App\Http\Requests\HrmLookupRequest;
use App\Http\Resources\MetadataResource;
use App\Models\HrmLookup;
use App\Models\HrmLookupGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HrmLookupController extends Controller
{
    // --- Lookup Groups ---

    public function indexGroups(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);
        $groups = HrmLookupGroup::paginate($limit);

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($groups)->response()->getData(true),
        ]);
    }

    public function storeGroup(HrmLookupGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $group = HrmLookupGroup::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lookup group created successfully.',
            'data' => new MetadataResource($group),
        ], 201);
    }

    public function showGroup(HrmLookupGroup $hrmLookupGroup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MetadataResource($hrmLookupGroup),
        ]);
    }

    public function updateGroup(HrmLookupGroupRequest $request, HrmLookupGroup $hrmLookupGroup): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $hrmLookupGroup->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Lookup group updated successfully.',
            'data' => new MetadataResource($hrmLookupGroup),
        ]);
    }

    public function destroyGroup(HrmLookupGroup $hrmLookupGroup): JsonResponse
    {
        $hrmLookupGroup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lookup group deleted successfully.',
        ]);
    }

    // --- Lookup Items ---

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'required_without:group_slug|nullable|string',
            'group_slug' => 'required_without:group_id|nullable|string',
        ]);

        $limit = $request->get('limit', 15);
        $query = HrmLookup::query();

        if ($request->filled('group_id')) {
            $query->whereHas('hrmLookupGroup', function ($q) use ($request) {
                $q->where('uuid', $request->group_id);
            });
        } elseif ($request->filled('group_slug')) {
            $query->whereHas('hrmLookupGroup', function ($q) use ($request) {
                $q->where('slug', $request->group_slug);
            });
        }

        $lookups = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($lookups)->response()->getData(true),
        ]);
    }

    public function store(HrmLookupRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $lookup = HrmLookup::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lookup item created successfully.',
            'data' => new MetadataResource($lookup),
        ], 201);
    }

    public function show(HrmLookup $hrmLookup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MetadataResource($hrmLookup),
        ]);
    }

    public function update(HrmLookupRequest $request, HrmLookup $hrmLookup): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $hrmLookup->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Lookup item updated successfully.',
            'data' => new MetadataResource($hrmLookup),
        ]);
    }

    public function destroy(HrmLookup $hrmLookup): JsonResponse
    {
        $hrmLookup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lookup item deleted successfully.',
        ]);
    }
}
