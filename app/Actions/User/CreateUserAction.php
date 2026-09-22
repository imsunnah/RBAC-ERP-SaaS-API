<?php

namespace App\Actions\User;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateUserAction
{
    public function __construct(
        private UserRepository $userRepository,
        private AuditService $auditService
    ) {}

    /**
     * Execute the creation of a user, organization attachment, and roles.
     */
    public function execute(array $payload, ?int $orgId = null): User
    {
        return DB::transaction(function () use ($payload, $orgId) {
            $payload['password']       = Hash::make($payload['password']);
            $payload['name']           = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''));
            $payload['is_super_admin'] = false;

            // Resolve selected organization ID
            $selectedOrgId = $payload['organization_id'] ?? $orgId;
            if (is_array($selectedOrgId)) {
                $selectedOrgId = reset($selectedOrgId);
            }
            if (!is_numeric($selectedOrgId)) {
                $selectedOrgId = Organization::where('uuid', $selectedOrgId)->value('id');
            }

            if (!is_numeric($selectedOrgId) || (int) $selectedOrgId < 1) {
                throw ValidationException::withMessages([
                    'organization_id' => 'A valid organization is required.',
                ]);
            }

            $selectedOrgId = (int) $selectedOrgId;

            $payload['organization_id'] = $selectedOrgId;

            $user = $this->userRepository->create($payload);

            // Attach only the selected organization
            $user->organizations()->attach($selectedOrgId, [
                'is_owner'   => false,
                'is_primary' => true,
                'status'     => 'active',
                'joined_at'  => now(),
            ]);

            if (!empty($payload['roles'])) {
                $firstRole = reset($payload['roles']);
                if (is_numeric($firstRole)) {
                    $roleIds = collect($payload['roles']);
                } else {
                    $roleIds = Role::whereIn('uuid', $payload['roles'])->pluck('id');
                }

                $user->roles()->attach(
                    $roleIds->mapWithKeys(fn ($rId) => [$rId => ['organization_id' => $selectedOrgId]])->toArray()
                );
            }

            $this->auditService->logCreated($user);

            return $this->userRepository->findWithOrgRoles($user->id, (string) $selectedOrgId);
        });
    }
}
