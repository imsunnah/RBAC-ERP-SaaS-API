<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sidebar\SubmenuRequest;
use App\Http\Resources\Sidebar\SubmenuResource;
use App\Models\Submenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmenuController extends Controller
{
    /**
     * GET /api/v1/submenus
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Submenu::class);

        $organization = app('current_organization');
        $activeModuleIds = $organization->activeModules()->pluck('modules.id');

        $submenus = Submenu::with(['menu.module', 'permission'])
            ->whereHas('menu', function ($q) use ($activeModuleIds) {
                $q->whereIn('module_id', $activeModuleIds);
            })
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => SubmenuResource::collection($submenus),
        ]);
    }

    /**
     * POST /api/v1/submenus
     */
    public function store(SubmenuRequest $request): JsonResponse
    {
        $this->authorize('create', Submenu::class);

        $submenu = Submenu::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Submenu created successfully.',
            'data' => new SubmenuResource($submenu->load(['menu', 'permission'])),
        ], 201);
    }

    /**
     * GET /api/v1/submenus/{submenu}
     */
    public function show(Submenu $submenu): JsonResponse
    {
        $this->authorize('view', $submenu);

        return response()->json([
            'success' => true,
            'data' => new SubmenuResource($submenu->load(['menu', 'permission'])),
        ]);
    }

    /**
     * PUT /api/v1/submenus/{submenu}
     */
    public function update(SubmenuRequest $request, Submenu $submenu): JsonResponse
    {
        $this->authorize('update', $submenu);

        $submenu->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Submenu updated successfully.',
            'data' => new SubmenuResource($submenu->load(['menu', 'permission'])),
        ]);
    }

    /**
     * DELETE /api/v1/submenus/{submenu}
     */
    public function destroy(Submenu $submenu): JsonResponse
    {
        $this->authorize('delete', $submenu);

        $submenu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Submenu deleted successfully.',
        ]);
    }
}
