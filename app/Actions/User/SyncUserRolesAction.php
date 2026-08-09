<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;

class SyncUserRolesAction
{
    public function __construct(
        private UserRepository $userRepository,
        private AuditService $auditService,
        private RBACService $rbacService
    ) {}

    /**
     * Execute syncing user roles for an organization.
     */
    public function execute(User $user, array $roleUuids, int $orgId): User
    {
        $roleIds   = Role::whereIn('uuid', $roleUuids)->pluck('id');
        $rolesData = $roleIds->mapWithKeys(fn ($roleId) => [
            $roleId => ['organization_id' => $orgId],
        ])->toArray();

        $user->roles()->wherePivot('organization_id', $orgId)->detach();
        $user->roles()->attach($rolesData);

        $this->rbacService->flushCache($user->id, $orgId);
        $this->auditService->logAction('update', $user, 'roles', ['roles' => $roleUuids]);

        return $this->userRepository->findWithOrgRoles($user->id, $orgId);
    }
}
