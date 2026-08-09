<?php

namespace App\Actions\Permission;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;

class SyncRolePermissionsAction
{
    public function __construct(
        private AuditService $auditService,
        private RBACService $rbacService
    ) {}

    /**
     * Execute syncing permissions for a specific role.
     */
    public function execute(Role $role, array $incomingPermissions, int $orgId): void
    {
        $uuids         = array_column($incomingPermissions, 'id');
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

        $role->permissions()->sync($syncData);

        $role->users->each(fn ($u) => $this->rbacService->flushCache($u->id, $orgId));
        $this->auditService->logAction('update', $role, 'permissions', $incomingPermissions);
    }
}
