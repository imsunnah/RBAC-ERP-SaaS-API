<?php

namespace App\Actions\HrmLeaveApplication;

use App\Actions\HrmLeaveApplication\Concerns\HandlesLeaveDocuments;
use App\Models\HrmLeaveApplication;

class DeleteHrmLeaveApplicationAction
{
    use HandlesLeaveDocuments;

    /**
     * Execute the action to delete a leave application and its documents.
     */
    public function execute(HrmLeaveApplication $leave): void
    {
        $this->deleteAllDocuments($leave);
        $leave->delete();
    }
}
