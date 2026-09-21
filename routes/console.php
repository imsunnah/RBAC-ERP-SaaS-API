<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('hrm:auto-select-meals')->weeklyOn(6, '00:00');
Schedule::command('hrm:generate-annual-leave-balances')
    ->yearlyOn(1, 1, '00:05');
