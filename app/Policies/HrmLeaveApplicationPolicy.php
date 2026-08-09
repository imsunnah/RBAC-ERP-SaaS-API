<?php

namespace App\Policies;

use App\Models\HrmLeaveApplication;
use App\Models\User;

class HrmLeaveApplicationPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.leave.view');
    }

    public function view(User $user, HrmLeaveApplication $leave): bool
    {
        return $this->checkPermission($user, 'hrm.leave.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.leave.create');
    }

    public function update(User $user, HrmLeaveApplication $leave): bool
    {
        return $this->checkPermission($user, 'hrm.leave.update');
    }

    public function delete(User $user, HrmLeaveApplication $leave): bool
    {
        return $this->checkPermission($user, 'hrm.leave.delete');
    }

    public function approve(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.leave.approve');
    }
}
