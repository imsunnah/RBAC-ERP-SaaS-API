<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Permission\PermissionResource;
use App\Http\Requests\Permission\SyncRolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private RBACService $rbacService,
    ) {
    }

    /**
     * GET /api/v1/permissions
     * List all available permissions grouped by module.
     */
    public function index(Request $request): JsonResponse
    {
        // For a multi-org structure, permissions are global (defined by modules).
        // Only fetch permissions for modules active in this organization.
        $activeModuleIds = app('current_organization')->activeModules()->pluck('modules.id');

        $permissions = Permission::with('module')
            ->whereIn('module_id', $activeModuleIds)
            ->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * PUT /api/v1/roles/{role}/permissions
     * Assign / Sync permissions to a role.
     */
    public function sync(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if ($role->organization_id !== $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $incomingPermissions = $request->validated('permissions');
        $uuids = array_column($incomingPermissions, 'id');
        $permissionMap = Permission::whereIn('uuid', $uuids)->get(['id', 'uuid'])->keyBy('uuid');

        $syncData = [];
        foreach ($incomingPermissions as $perm) {
            $permission = $permissionMap[$perm['id']] ?? null;
            if ($permission) {
                $syncData[$permission->id] = [
                    'is_granted' => $perm['is_granted'],
                ];
            }
        }

        // Only sync explicitly requested, detaching anything not in the list
        $role->permissions()->sync($syncData);

        // Invalidate cache for all users holding this role
        $role->users->each(fn($u) => $this->rbacService->flushCache($u->id, $orgId));

        $this->auditService->logAction('update', $role, 'permissions', $incomingPermissions);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully.'
        ]);
    }
}
