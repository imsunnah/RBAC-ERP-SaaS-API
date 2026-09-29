<?php

use App\Models\HrmEmployee;
use App\Models\WorkShift;
use App\Services\HrmLeave\LeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('leave calculation uses weekends from the employee work shift', function () {
    $employee = new HrmEmployee();
    $employee->setRelation('workShift', new WorkShift(['weekend' => ['friday']]));

    $days = app(LeaveCalculationService::class)->calculateDays(
        Carbon::parse('2026-09-25'),
        Carbon::parse('2026-09-27'),
        'full_day',
        'full_day',
        $employee
    );

    expect($days)->toBe(2.0);
});