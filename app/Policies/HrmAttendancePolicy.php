<?php

namespace App\Policies;

use App\Models\HrmAttendance;
use App\Models\User;

class HrmAttendancePolicy extends OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.attendance.view');
    }

    public function view(User $user, HrmAttendance $attendance): bool
    {
        return $this->checkPermission($user, 'hrm.attendance.view');
    }

    public function export(User $user): bool
    {
        return $this->checkPermission($user, 'hrm.attendance.export');
    }
}
