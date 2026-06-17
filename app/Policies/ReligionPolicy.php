<?php

namespace App\Policies;

use App\Models\Religion;
use App\Models\User;

class ReligionPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.religion.view');
    }

    public function view(User $user, Religion $religion): bool
    {
        return $this->checkPermission($user, 'admin.religion.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.religion.create');
    }

    public function update(User $user, Religion $religion): bool
    {
        return $this->checkPermission($user, 'admin.religion.update');
    }

    public function delete(User $user, Religion $religion): bool
    {
        return $this->checkPermission($user, 'admin.religion.delete');
    }
}
