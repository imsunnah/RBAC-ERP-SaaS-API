<?php

namespace App\Actions\HrmEmployee;

use App\Actions\HrmEmployee\Concerns\HandlesEmployeeMedia;
use App\Models\HrmEmployee;

class DeleteHrmEmployeeAction
{
    use HandlesEmployeeMedia;

    /**
     * Execute the action to delete an employee and clean up uploaded media files.
     */
    public function execute(HrmEmployee $employee): void
    {
        $this->deleteAllMedia($employee);
        $employee->delete();
    }
}
