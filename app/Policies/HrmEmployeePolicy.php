<?php

namespace App\Policies;

use App\Models\HrmEmployee;
use App\Models\User;

class HrmEmployeePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.employee.view');
    }

    public function view(User $user, HrmEmployee $employee): bool
    {
        return $this->checkPermission($user, 'hrm.employee.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.employee.create');
    }

    public function update(User $user, HrmEmployee $employee): bool
    {
        return $this->checkPermission($user, 'hrm.employee.update');
    }

    public function delete(User $user, HrmEmployee $employee): bool
    {
        return $this->checkPermission($user, 'hrm.employee.delete');
    }

    public function export(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.employee.export');
    }
}
