<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.department.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->checkPermission($user, 'admin.department.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.department.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->checkPermission($user, 'admin.department.update');
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->checkPermission($user, 'admin.department.delete');
    }
}
