<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.role.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->checkPermission($user, 'admin.role.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.role.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->checkPermission($user, 'admin.role.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->checkPermission($user, 'admin.role.delete');
    }

    public function assign(User $user): bool
    {
        return $this->checkPermission($user, 'admin.role.assign');
    }
}
