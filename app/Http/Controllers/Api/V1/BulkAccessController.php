<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;
use Illuminate\Http\JsonResponse;

class BulkAccessController extends Controller
{
    public function __construct(
        private AuditService $auditService,
        private RBACService $rbacService,
    ) {
    }

    /**
     * POST /api/v1/roles/{role}/users/bulk
     * Bulk assign a role to multiple users in the current organization.
     */
    public function bulkAssignRole(BulkAccessRequest $request, Role $role): JsonResponse
    {
        $orgId = app('current_organization')->id;

        if ((int) $role->organization_id !== (int) $orgId) {
            return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
        }

        $userIds = $request->validated('user_ids');
        $users = User::whereIn('uuid', $userIds)->get();

        foreach ($users as $user) {
            // Verify user belongs to the organization
            if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
                continue;
            }

            // Sync without detaching existing roles to avoid losing their other roles
            $user->roles()->syncWithoutDetaching([
                $role->id => ['organization_id' => $orgId]
            ]);

            $this->rbacService->flushCache($user->id, $orgId);
        }

        $this->auditService->logAction('update', $role, 'bulk_assign_role', ['user_uuids' => $userIds]);

        return response()->json([
            'success' => true,
            'message' => 'Role assigned to users successfully.'
        ]);
    }

    /**
     * POST /api/v1/permissions/bulk-assign
     * Bulk assign permissions to multiple users directly.
     */
    public function bulkAssignPermissions(BulkAccessRequest $request): JsonResponse
    {
        $orgId = app('current_organization')->id;
        $userIds = $request->validated('user_ids');
        $incomingPermissions = $request->validated('permissions');
        
        $users = User::whereIn('uuid', $userIds)->get();
        
        $uuids = array_column($incomingPermissions, 'id');
        $permissionMap = Permission::whereIn('uuid', $uuids)->get(['id', 'uuid'])->keyBy('uuid');

        $syncData = [];
        foreach ($incomingPermissions as $perm) {
            $permission = $permissionMap[$perm['id']] ?? null;
            if ($permission) {
                $syncData[$permission->id] = [
                    'organization_id' => $orgId,
                    'is_granted' => $perm['is_granted'],
                ];
            }
        }

        foreach ($users as $user) {
            // Verify user belongs to the organization
            if (!$user->organizations()->where('organizations.id', $orgId)->exists()) {
                continue;
            }

            $user->permissions()->syncWithoutDetaching($syncData);
            $this->rbacService->flushCache($user->id, $orgId);
        }

        $this->auditService->logAction('update', null, 'bulk_assign_permissions', [
            'user_uuids' => $userIds,
            'permissions' => $incomingPermissions
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to users successfully.'
        ]);
    }
}
