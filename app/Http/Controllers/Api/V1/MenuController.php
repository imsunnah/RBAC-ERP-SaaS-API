<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sidebar\MenuRequest;
use App\Http\Resources\Sidebar\MenuResource;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * GET /api/v1/menus
     */
    public function index(Request $request): JsonResponse
    {
        $organization = app('current_organization');
        $activeModuleIds = $organization->activeModules()->pluck('modules.id');

        $menus = Menu::with(['module', 'submenus.permission'])
            ->whereIn('module_id', $activeModuleIds)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => MenuResource::collection($menus),
        ]);
    }

    /**
     * POST /api/v1/menus
     */
    public function store(MenuRequest $request): JsonResponse
    {
        $menu = Menu::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Menu created successfully.',
            'data' => new MenuResource($menu->load(['module', 'submenus'])),
        ], 201);
    }

    /**
     * GET /api/v1/menus/{menu}
     */
    public function show(Menu $menu): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MenuResource($menu->load(['module', 'submenus'])),
        ]);
    }

    /**
     * PUT /api/v1/menus/{menu}
     */
    public function update(MenuRequest $request, Menu $menu): JsonResponse
    {
        $menu->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully.',
            'data' => new MenuResource($menu->load(['module', 'submenus'])),
        ]);
    }

    /**
     * DELETE /api/v1/menus/{menu}
     */
    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully.',
        ]);
    }
}
