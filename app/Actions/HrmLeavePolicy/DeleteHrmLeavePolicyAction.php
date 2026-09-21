<?php

namespace App\Actions\HrmLeavePolicy;

use App\Models\HrmLeavePolicy;

class DeleteHrmLeavePolicyAction
{
    public function execute(HrmLeavePolicy $policy): void
    {
        $policy->delete();
    }
}
