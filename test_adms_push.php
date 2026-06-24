<?php

use App\Models\HrmEmployee;
use App\Models\AdmsDevice;
use App\Http\Controllers\Api\V1\AdmsPushController;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap without kernel handle to avoid migration trigger
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "DB Connection: " . config('database.default') . "\n";
echo "DB Database: " . config('database.connections.' . config('database.default') . '.database') . "\n";
echo "DB Host: " . config('database.connections.' . config('database.default') . '.host') . "\n";
echo "DB Username: " . config('database.connections.' . config('database.default') . '.username') . "\n";
echo "Env DB_DATABASE: " . env('DB_DATABASE') . "\n";
echo "HrmAttendance Table: " . (new \App\Models\HrmAttendance())->getTable() . "\n";

// 1. Find an employee with biometric_id
$employee = HrmEmployee::whereNotNull('biometric_id')->first();
if (!$employee) {
    echo "No employee with biometric_id found. Creating a dummy one...\n";
    $employee = HrmEmployee::create([
        'biometric_id' => '9999',
        'full_name' => 'Test Employee',
        'org_no' => 1,
        'company_id' => 1,
        'organization_id' => 1,
        'uuid' => \Illuminate\Support\Str::uuid()
    ]);
}

$pin = $employee->biometric_id;
$sn = "TESTSN12345";
echo "Testing for Employee: {$employee->full_name} (ID: {$employee->id}, PIN: {$pin})\n";
echo "Using Organization ID: {$employee->organization_id}\n";

// 2. Ensure device exists
$device = AdmsDevice::heartbeat($sn, ['ip_address' => '127.0.0.1']);

$controller = $app->make(AdmsPushController::class);

// 3. Simulate first punch (Check-in)
$date = date('Y-m-d');
$time1 = "09:05:23";
$body1 = "{$pin}\t{$date}\t{$time1}\t0\t0\t0\t0\n";

echo "Simulating First Punch: {$time1}...\n";
$request1 = Request::create('/attn/push', 'POST', ['SN' => $sn, 'table' => 'ATTLOG'], [], [], [], $body1);
$response1 = $controller->handle($request1);
echo "Response: " . $response1->getContent();

// 4. Simulate second punch (Check-out)
$time2 = "17:15:45";
$body2 = "{$pin}\t{$date}\t{$time2}\t1\t0\t0\t0\n";

echo "Simulating Second Punch: {$time2}...\n";
$request2 = Request::create('/attn/push', 'POST', ['SN' => $sn, 'table' => 'ATTLOG'], [], [], [], $body2);
$response2 = $controller->handle($request2);
echo "Response: " . $response2->getContent();

// 5. Verify database
$attendance = \App\Models\HrmAttendance::withoutGlobalScopes()
    ->where('employee_id', $employee->id)
    ->where('date', $date)
    ->first();

if ($attendance) {
    echo "\nSUCCESS: Attendance record found!\n";
    echo "Date: {$attendance->date->toDateString()}\n";
    echo "Punch-in: {$attendance->punch_in}\n";
    echo "Punch-out: {$attendance->punch_out}\n";
    echo "Working Hours: {$attendance->working_hours}\n";
    echo "Status: {$attendance->status}\n";
} else {
    echo "\nFAILURE: Attendance record not found.\n";
}
