<?php

namespace App\Actions\User;

use App\Models\HrmEmployee;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function __construct(
        private UserRepository $userRepository,
        private AuditService $auditService
    ) {}

    /**
     * Execute the creation of a user, organization attachment, and HRM employee record.
     */
    public function execute(array $payload, int $orgId): User
    {
        $payload['password']        = Hash::make($payload['password']);
        $payload['name']            = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''));
        $payload['is_super_admin']  = false;
        $payload['organization_id'] = $orgId;

        $user = $this->userRepository->create($payload);

        $orgIdsToAttach = [$orgId];
        if (!empty($payload['organizations'])) {
            $extraOrgIds    = Organization::whereIn('uuid', $payload['organizations'])->pluck('id')->toArray();
            $orgIdsToAttach = array_unique(array_merge($orgIdsToAttach, $extraOrgIds));
        }

        foreach ($orgIdsToAttach as $idToAttach) {
            $user->organizations()->attach($idToAttach, [
                'is_owner'   => false,
                'is_primary' => ($idToAttach === $orgId),
                'status'     => 'active',
                'joined_at'  => now(),
            ]);
        }

        if (!empty($payload['roles'])) {
            $roleIds = Role::whereIn('uuid', $payload['roles'])->pluck('id');
            $user->roles()->attach(
                $roleIds->mapWithKeys(fn ($roleId) => [$roleId => ['organization_id' => $orgId]])->toArray()
            );
        }

        // Create HRM Employee record
        HrmEmployee::create([
            'company_id'       => $user->company_id,
            'organization_id'  => $orgId,
            'full_name'        => $user->name,
            'office_email'     => $user->email,
            'personal_email'   => $user->email,
            'is_active'        => true,
            'user_employee_id' => substr((string) $user->id, 0, 16),
            'created_by'       => Auth::id() ?? $user->id,
        ]);

        $this->auditService->logCreated($user);

        return $this->userRepository->findWithOrgRoles($user->id, $orgId);
    }
}
