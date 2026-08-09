<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Permission\SyncRolePermissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\PermissionRequest;
use App\Http\Requests\Permission\SyncRolePermissionsRequest;
use App\Http\Resources\Permission\PermissionResource;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /** GET /api/v1/permissions */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $activeModuleIds = app('current_organization')->activeModules()->pluck('modules.id');

        $permissions = Permission::with('module')
            ->whereIn('module_id', $activeModuleIds)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => PermissionResource::collection($permissions),
        ]);
    }

    /** GET /api/v1/permissions/grouped */
    public function groupedByModule(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $activeModuleIds = app('current_organization')->activeModules()->pluck('modules.id');

        $modules = Module::whereIn('id', $activeModuleIds)
            ->with(['permissions'])
            ->get();

        $grouped = $modules->map(function ($module) {
            $perms = $module->permissions->map(function ($perm) {
                $parts  = explode('.', $perm->slug);
                $action = end($parts);

                return [
                    'id'     => $perm->uuid,
                    'name'   => $perm->name,
                    'slug'   => $perm->slug,
                    'action' => in_array($action, ['create', 'read', 'update', 'delete'], true) ? $action : 'other',
                ];
            });

            return [
                'id'          => $module->uuid,
                'name'        => $module->name,
                'slug'        => $module->slug,
                'icon'        => $module->icon,
                'permissions' => $perms,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    /** POST /api/v1/permissions */
    public function store(PermissionRequest $request): JsonResponse
    {
        $this->authorize('create', Permission::class);

        $permission = Permission::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully.',
            'data'    => new PermissionResource($permission->load('module')),
        ], 201);
    }

    /** GET /api/v1/permissions/{permission} */
    public function show(Permission $permission): JsonResponse
    {
        $this->authorize('view', $permission);

        return response()->json([
            'success' => true,
            'data'    => new PermissionResource($permission->load('module')),
        ]);
    }

    /** PUT /api/v1/permissions/{permission} */
    public function update(PermissionRequest $request, Permission $permission): JsonResponse
    {
        $this->authorize('update', $permission);

        $permission->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully.',
            'data'    => new PermissionResource($permission->load('module')),
        ]);
    }

    /** DELETE /api/v1/permissions/{permission} */
    public function destroy(Permission $permission): JsonResponse
    {
        $this->authorize('delete', $permission);

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
        ]);
    }

    /** PUT /api/v1/roles/{role}/permissions */
    public function sync(
        SyncRolePermissionsRequest $request,
        Role $role,
        SyncRolePermissionsAction $action
    ): JsonResponse {
        $this->authorize('assign', Permission::class);

        $orgId = app('current_organization')->id;

        if ((int) $role->organization_id !== (int) $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $action->execute($role, $request->validated('permissions'), $orgId);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully.',
        ]);
    }
}
