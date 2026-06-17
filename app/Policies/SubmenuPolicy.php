<?php

namespace App\Policies;

use App\Models\Submenu;
use App\Models\User;

class SubmenuPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'admin.submenu.view');
    }

    public function view(User $user, Submenu $submenu): bool
    {
        return $this->checkPermission($user, 'admin.submenu.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'admin.submenu.create');
    }

    public function update(User $user, Submenu $submenu): bool
    {
        return $this->checkPermission($user, 'admin.submenu.update');
    }

    public function delete(User $user, Submenu $submenu): bool
    {
        return $this->checkPermission($user, 'admin.submenu.delete');
    }
}
