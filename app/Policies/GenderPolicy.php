<?php

namespace App\Policies;

use App\Models\Gender;
use App\Models\User;

class GenderPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.gender.view');
    }

    public function view(User $user, Gender $gender): bool
    {
        return $this->checkPermission($user, 'admin.gender.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.gender.create');
    }

    public function update(User $user, Gender $gender): bool
    {
        return $this->checkPermission($user, 'admin.gender.update');
    }

    public function delete(User $user, Gender $gender): bool
    {
        return $this->checkPermission($user, 'admin.gender.delete');
    }
}
