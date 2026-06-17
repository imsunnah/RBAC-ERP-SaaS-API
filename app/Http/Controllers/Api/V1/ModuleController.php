<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Module\ModuleResource;
use App\Http\Requests\Module\ModuleRequest;
use App\Http\Requests\Module\ModuleUpdateRequest;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * GET /api/v1/modules
     * Lists modules the current user can access.
     * Filters by: org active modules ∩ role-assigned modules.
     * Super admins see all organization modules.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Module::class);

        $user = $request->user();
        $organization = app('current_organization');

        // Super admins see all active modules for the organization
        if ($user->is_super_admin) {
            $modules = $organization->activeModules()
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ModuleResource::collection($modules),
            ]);
        }

        // Regular users: intersection of org active modules AND role-assigned modules
        $roleIds = $user->roles()
            ->wherePivot('organization_id', $organization->id)
            ->pluck('roles.id');

        $roleModuleIds = \Illuminate\Support\Facades\DB::table('role_modules')
            ->whereIn('role_id', $roleIds)
            ->pluck('module_id')
            ->unique()
            ->toArray();

        $modules = $organization->activeModules()
            ->whereIn('modules.id', $roleModuleIds)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ModuleResource::collection($modules),
        ]);
    }

    /**
     * POST /api/v1/modules
     */
    public function store(ModuleRequest $request): JsonResponse
    {
        $this->authorize('create', Module::class);

        $module = Module::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully.',
            'data' => new ModuleResource($module),
        ], 201);
    }

    /**
     * GET /api/v1/modules/{module}
     */
    public function show(Module $module): JsonResponse
    {
        $this->authorize('view', $module);

        return response()->json([
            'success' => true,
            'data' => new ModuleResource($module),
        ]);
    }

    /**
     * PUT /api/v1/modules/{module}
     */
    public function update(ModuleUpdateRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $module->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully.',
            'data' => new ModuleResource($module),
        ]);
    }

    /**
     * DELETE /api/v1/modules/{module}
     */
    public function destroy(Module $module): JsonResponse
    {
        $this->authorize('delete', $module);

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully.',
        ]);
    }
}
