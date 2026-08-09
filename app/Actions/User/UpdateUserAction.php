<?php

namespace App\Actions\User;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Hash;

class UpdateUserAction
{
    public function __construct(
        private UserRepository $userRepository,
        private AuditService $auditService
    ) {}

    /**
     * Execute the update of a user record and organization bindings.
     */
    public function execute(User $user, array $payload, int $orgId): User
    {
        if (!empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        } else {
            unset($payload['password']);
        }

        if (isset($payload['first_name']) || isset($payload['last_name'])) {
            $firstName       = $payload['first_name'] ?? $user->first_name;
            $lastName        = $payload['last_name'] ?? $user->last_name;
            $payload['name'] = trim($firstName . ' ' . $lastName);
        }

        $original = $user->getAttributes();
        $this->userRepository->update($user, $payload);

        if (isset($payload['organizations'])) {
            $extraOrgIds  = Organization::whereIn('uuid', $payload['organizations'])->pluck('id')->toArray();
            $orgIdsToSync = array_unique(array_merge([$orgId], $extraOrgIds));

            $syncData = [];
            foreach ($orgIdsToSync as $idToSync) {
                $syncData[$idToSync] = [
                    'is_owner' => false,
                    'status'   => 'active',
                ];
            }
            $user->organizations()->syncWithoutDetaching($syncData);
        }

        $this->auditService->logUpdated($user->fresh(), $original);

        return $this->userRepository->findWithOrgRoles($user->id, $orgId);
    }
}
