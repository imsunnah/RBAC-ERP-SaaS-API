<?php

namespace App\Policies;

use App\Models\HrmEmployeeMealSelection;
use App\Models\User;

class HrmEmployeeMealSelectionPolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.meal.view');
    }

    public function view(User $user, HrmEmployeeMealSelection $selection): bool
    {
        return $this->checkPermission($user, 'hrm.meal.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.meal.create');
    }

    public function update(User $user, HrmEmployeeMealSelection $selection): bool
    {
        return $this->checkPermission($user, 'hrm.meal.update');
    }

    public function delete(User $user, HrmEmployeeMealSelection $selection): bool
    {
        return $this->checkPermission($user, 'hrm.meal.delete');
    }
}
