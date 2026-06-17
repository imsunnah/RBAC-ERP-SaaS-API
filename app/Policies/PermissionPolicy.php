<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.permission.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->checkPermission($user, 'admin.permission.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.permission.create');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->checkPermission($user, 'admin.permission.update');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->checkPermission($user, 'admin.permission.delete');
    }

    public function assign(User $user): bool
    {
        return $this->checkPermission($user, 'admin.permission.assign');
    }
}
