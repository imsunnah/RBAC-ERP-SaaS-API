<?php

namespace App\Policies;

use App\Models\Designation;
use App\Models\User;

class DesignationPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.designation.view');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $this->checkPermission($user, 'admin.designation.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.designation.create');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $this->checkPermission($user, 'admin.designation.update');
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $this->checkPermission($user, 'admin.designation.delete');
    }
}
