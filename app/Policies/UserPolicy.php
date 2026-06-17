<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.user.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->checkPermission($user, 'admin.user.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->checkPermission($user, 'admin.user.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $this->checkPermission($user, 'admin.user.delete');
    }
}
