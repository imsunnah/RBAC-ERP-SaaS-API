<?php

namespace App\Actions\Role;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;
use Illuminate\Support\Facades\Cache;

class SyncRoleAllPermissionsAction
{
    public function __construct(
        private AuditService $auditService,
        private RBACService $rbacService
    ) {}

    /**
     * Execute full role sync (modules, menus, submenus, permissions).
     */
    public function execute(Role $role, array $validated, int $orgId): void
    {
        // Sync modules
        $role->modules()->sync($validated['modules'] ?? []);

        // Sync menus & submenus
        $role->menus()->sync($validated['menus'] ?? []);
        $role->submenus()->sync($validated['submenus'] ?? []);

        // Sync permissions (UUID → ID resolution with single batch query)
        $incomingPermissions = $validated['permissions'] ?? [];
        $uuids               = array_column($incomingPermissions, 'id');
        $permissionMap       = Permission::whereIn('uuid', $uuids)->get(['id', 'uuid'])->keyBy('uuid');

        $syncData = [];
        foreach ($incomingPermissions as $perm) {
            $permission = $permissionMap[$perm['id']] ?? null;
            if ($permission) {
                $syncData[$permission->id] = ['is_granted' => $perm['is_granted']];
            }
        }
        $role->permissions()->sync($syncData);

        // Flush caches
        $role->users->each(function ($u) use ($orgId) {
            $this->rbacService->flushCache($u->id, $orgId);
            Cache::forget("erp:sidebar:{$orgId}:{$u->id}");
        });

        $this->auditService->logAction('update', $role, 'role_sync_all', [
            'modules'     => $validated['modules'] ?? [],
            'menus'       => $validated['menus'] ?? [],
            'submenus'    => $validated['submenus'] ?? [],
            'permissions' => $incomingPermissions,
        ]);
    }
}
