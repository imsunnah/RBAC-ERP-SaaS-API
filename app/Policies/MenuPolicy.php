<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.menu.view');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->checkPermission($user, 'admin.menu.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.menu.create');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $this->checkPermission($user, 'admin.menu.update');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->checkPermission($user, 'admin.menu.delete');
    }
}
