<?php

namespace App\Actions\HrmWeeklyMeal;

use App\Models\HrmWeeklyMeal;

class DeleteHrmWeeklyMealAction
{
    /**
     * Execute deletion of a weekly meal setup.
     */
    public function execute(HrmWeeklyMeal $meal): void
    {
        $meal->delete();
    }
}
