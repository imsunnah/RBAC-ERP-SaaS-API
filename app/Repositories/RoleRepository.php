<?php

namespace App\Repositories;

use App\Models\Role;
use App\Repositories\Base\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository extends BaseRepository
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function getByOrganization(string $orgId, int $perPage = 15)
    {
        return Role::where('organization_id', $orgId)
            ->with(['permissions', 'parentRole:id,name,slug'])
            ->paginate($perPage);
    }

    public function findInOrg(string $roleId, string $orgId): ?Role
    {
        return Role::where('id', $roleId)
            ->where('organization_id', $orgId)
            ->with(['permissions', 'modules', 'menus', 'submenus'])
            ->first();
    }

    /**
     * Get all descendant roles via parent_role_id chain.
     */
    public function getDescendants(string $roleId): Collection
    {
        $descendants = collect();
        $this->collectDescendants($roleId, $descendants);
        return $descendants;
    }

    private function collectDescendants(string $roleId, \Illuminate\Support\Collection &$result): void
    {
        $children = Role::where('parent_role_id', $roleId)->get();
        foreach ($children as $child) {
            $result->push($child);
            $this->collectDescendants($child->id, $result);
        }
    }
}
