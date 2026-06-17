<?php

namespace App\Policies;

use App\Models\MaritalStatus;
use App\Models\User;

class MaritalStatusPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.marital-status.view');
    }

    public function view(User $user, MaritalStatus $maritalStatus): bool
    {
        return $this->checkPermission($user, 'admin.marital-status.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.marital-status.create');
    }

    public function update(User $user, MaritalStatus $maritalStatus): bool
    {
        return $this->checkPermission($user, 'admin.marital-status.update');
    }

    public function delete(User $user, MaritalStatus $maritalStatus): bool
    {
        return $this->checkPermission($user, 'admin.marital-status.delete');
    }
}
