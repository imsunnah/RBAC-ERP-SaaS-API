<?php

namespace App\Policies;

use App\Models\HrmLeaveBalance;
use App\Models\User;

class HrmLeaveBalancePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.leave.view');
    }

    public function view(User $user, HrmLeaveBalance $balance): bool
    {
        return $this->checkPermission($user, 'hrm.leave.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.leave.create');
    }

    public function update(User $user, HrmLeaveBalance $balance): bool
    {
        return $this->checkPermission($user, 'hrm.leave.update');
    }

    public function delete(User $user, HrmLeaveBalance $balance): bool
    {
        return $this->checkPermission($user, 'hrm.leave.delete');
    }
}
