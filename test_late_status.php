<?php

use App\Models\HrmEmployee;
use App\Models\AdmsDevice;
use App\Http\Controllers\Api\V1\AdmsPushController;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$employee = HrmEmployee::whereNotNull('biometric_id')->first();
$pin = $employee->biometric_id;
$sn = "TESTSN12345";
$date = date('Y-m-d', strtotime('+1 day')); // Test for tomorrow

$controller = $app->make(AdmsPushController::class);

echo "Testing Late Punch for PIN: {$pin} on {$date}...\n";

// First punch (Late) - 09:25:00 (Grace is 15 mins, Shift starts 09:00)
$time1 = "09:25:00";
$body1 = "{$pin}\t{$date}\t{$time1}\t0\t0\t0\t0\n";
$request1 = Request::create('/attn/push', 'POST', ['SN' => $sn, 'table' => 'ATTLOG'], [], [], [], $body1);
$controller->handle($request1);

$attendance = \App\Models\HrmAttendance::where('employee_id', $employee->id)
    ->where('date', $date)
    ->first();

if ($attendance && $attendance->status === 'LATE') {
    echo "SUCCESS: Late status correctly detected!\n";
} else {
    echo "FAILURE: Status was " . ($attendance->status ?? 'NOT FOUND') . "\n";
}
