<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all users for a specific organization with roles.
     */
    public function getByOrganization(string $orgId, int $perPage = 15)
    {
        return User::whereHas('userOrganizations', fn($q) => $q
            ->where('organization_id', $orgId)
            ->where('status', 'active'))
            ->with(['userOrganizations' => fn($q) => $q->where('organization_id', $orgId)])
            ->paginate($perPage);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Get user with all their roles for a given organization.
     */
    public function findWithOrgRoles(string $userId, string $orgId): ?User
    {
        return User::with(['roles' => fn($q) => $q->wherePivot('organization_id', $orgId)->with('permissions')])
            ->find($userId);
    }
}
