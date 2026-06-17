<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

class ModulePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.module.view');
    }

    public function view(User $user, Module $module): bool
    {
        return $this->checkPermission($user, 'admin.module.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.module.create');
    }

    public function update(User $user, Module $module): bool
    {
        return $this->checkPermission($user, 'admin.module.update');
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->checkPermission($user, 'admin.module.delete');
    }
}
