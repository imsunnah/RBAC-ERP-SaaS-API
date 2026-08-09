<?php

namespace App\Actions\User;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\RBAC\RBACService;

class DeleteUserAction
{
    public function __construct(
        private UserRepository $userRepository,
        private AuditService $auditService,
        private RBACService $rbacService
    ) {}

    /**
     * Execute removing user from organization and optional soft deletion.
     */
    public function execute(User $user, int $orgId): void
    {
        $user->organizations()->detach($orgId);

        // Remove roles for this org
        $user->roles()->wherePivot('organization_id', $orgId)->detach();

        $this->auditService->logDeleted($user);

        // Only soft-delete if this was their last organization
        if ($user->organizations()->count() === 0) {
            $this->userRepository->delete($user);
        }

        $this->rbacService->flushCache($user->id, $orgId);
    }
}
