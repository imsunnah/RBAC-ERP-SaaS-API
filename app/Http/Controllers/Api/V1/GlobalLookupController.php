<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalLookupGroupRequest;
use App\Http\Requests\GlobalLookupRequest;
use App\Http\Resources\MetadataResource;
use App\Models\GlobalLookup;
use App\Models\GlobalLookupGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalLookupController extends Controller
{
    // --- Lookup Groups ---

    public function getGroupedLookups(): JsonResponse
    {
        $groups = GlobalLookupGroup::with(['globalLookups' => function ($query) {
            $query->where('is_active', true);
        }])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups->map(function ($group) {
                return [
                    'group_uuid' => $group->uuid,
                    'group_name' => $group->name,
                    'group_slug' => $group->slug,
                    'values' => $group->globalLookups->map(function ($lookup) {
                        return [
                            'uuid' => $lookup->uuid,
                            'name' => $lookup->name,
                            'slug' => $lookup->slug,
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function getLookupsByGroupSlug(string $groupSlug): JsonResponse
    {
        $group = GlobalLookupGroup::with(['globalLookups' => function ($query) {
            $query->where('is_active', true);
        }])
            ->where('is_active', true)
            ->where('slug', $groupSlug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'group_uuid' => $group->uuid,
                'group_name' => $group->name,
                'group_slug' => $group->slug,
                'values' => $group->globalLookups->map(function ($lookup) {
                    return [
                        'uuid' => $lookup->uuid,
                        'name' => $lookup->name,
                        'slug' => $lookup->slug,
                    ];
                }),
            ],
        ]);
    }

    public function indexGroups(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);
        $groups = GlobalLookupGroup::paginate($limit);

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($groups)->response()->getData(true),
        ]);
    }

    public function storeGroup(GlobalLookupGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $group = GlobalLookupGroup::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Global lookup group created successfully.',
            'data' => new MetadataResource($group),
        ], 201);
    }

    public function showGroup(GlobalLookupGroup $globalLookupGroup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MetadataResource($globalLookupGroup),
        ]);
    }

    public function updateGroup(GlobalLookupGroupRequest $request, GlobalLookupGroup $globalLookupGroup): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $globalLookupGroup->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Global lookup group updated successfully.',
            'data' => new MetadataResource($globalLookupGroup),
        ]);
    }

    public function destroyGroup(GlobalLookupGroup $globalLookupGroup): JsonResponse
    {
        $globalLookupGroup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Global lookup group deleted successfully.',
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
        $query = GlobalLookup::query();

        if ($request->filled('group_id')) {
            $query->whereHas('globalLookupGroup', function ($q) use ($request) {
                $q->where('uuid', $request->group_id);
            });
        } elseif ($request->filled('group_slug')) {
            $query->whereHas('globalLookupGroup', function ($q) use ($request) {
                $q->where('slug', $request->group_slug);
            });
        }

        $lookups = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => MetadataResource::collection($lookups)->response()->getData(true),
        ]);
    }

    public function store(GlobalLookupRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $lookup = GlobalLookup::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Global lookup item created successfully.',
            'data' => new MetadataResource($lookup),
        ], 201);
    }

    public function show(GlobalLookup $globalLookup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MetadataResource($globalLookup),
        ]);
    }

    public function update(GlobalLookupRequest $request, GlobalLookup $globalLookup): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $globalLookup->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Global lookup item updated successfully.',
            'data' => new MetadataResource($globalLookup),
        ]);
    }

    public function destroy(GlobalLookup $globalLookup): JsonResponse
    {
        $globalLookup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Global lookup item deleted successfully.',
        ]);
    }
}
