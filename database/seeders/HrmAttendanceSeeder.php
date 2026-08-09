<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HrmAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Seeding hrm_attendances table...");

        // Fetch some existing employees to associate with attendance
        $employees = DB::table('hrm_employees')->limit(50)->get();

        if ($employees->isEmpty()) {
            $this->command->error("No employees found in hrm_employees table. Please seed employees first.");
            return;
        }

        // Get actual database columns dynamically
        $columns = Schema::getColumnListing('hrm_attendances');
        $hasColumn = function($name) use ($columns) {
            return in_array($name, $columns);
        };

        $data = [];
        // Seed 30 days of data
        $startDate = Carbon::now()->subDays(30);

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($employees as $employee) {
                // Skip attendance for this employee randomly (10% chance to simulate absent/leave)
                if (rand(1, 10) === 1) {
                    continue;
                }

                // Generate a random check-in time between 08:30 and 09:30
                $checkInTime = $date->copy()->setTime(8, rand(30, 59), rand(0, 59));
                if (rand(1, 10) > 8) {
                    // Late check-in
                    $checkInTime = $date->copy()->setTime(9, rand(1, 45), rand(0, 59));
                }

                // Generate a random check-out time between 17:00 and 19:30
                $checkOutTime = $date->copy()->setTime(17, rand(0, 59), rand(0, 59));
                if (rand(1, 10) > 7) {
                    $checkOutTime = $date->copy()->setTime(18, rand(0, 59), rand(0, 59));
                }

                // Calculate working hours
                $workingHours = round($checkInTime->diffInMinutes($checkOutTime) / 60, 2);
                $overtimeHours = $workingHours > 8 ? round($workingHours - 8, 2) : 0;

                $status = 'PRESENT';
                if ($checkInTime->format('H:i:s') > '09:00:00') {
                    $status = 'LATE';
                }

                // Dynamically build the record based on existing columns
                $record = [
                    'uuid' => (string) Str::uuid(),
                    'company_id' => $employee->company_id,
                    'organization_id' => $employee->organization_id,
                    'employee_id' => $employee->id,
                    'date' => $date->format('Y-m-d'),
                    'status' => $status,
                    'working_hours' => $workingHours,
                    'overtime_hours' => $overtimeHours,
                    'shift_id' => null,
                    'source' => 'MANUAL',
                    'approval_status' => 'APPROVED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($hasColumn('device_user_id')) {
                    $record['device_user_id'] = $employee->user_employee_id ?? 'EMP-' . $employee->id;
                }
                if ($hasColumn('biometric_id')) {
                    $record['biometric_id'] = $employee->biometric_id ?? (string) rand(1000, 9999);
                }
                if ($hasColumn('punch_in')) {
                    $record['punch_in'] = $checkInTime->format('Y-m-d H:i:s');
                }
                if ($hasColumn('check_in')) {
                    $record['check_in'] = $checkInTime->format('Y-m-d H:i:s');
                }
                if ($hasColumn('punch_out')) {
                    $record['punch_out'] = $checkOutTime->format('Y-m-d H:i:s');
                }
                if ($hasColumn('check_out')) {
                    $record['check_out'] = $checkOutTime->format('Y-m-d H:i:s');
                }
                if ($hasColumn('org_no')) {
                    $record['org_no'] = $employee->org_no ?? null;
                }

                $data[] = $record;
            }
        }

        // Insert in chunks
        foreach (array_chunk($data, 1000) as $chunk) {
            DB::table('hrm_attendances')->insert($chunk);
        }

        // Clean up columns.txt file if it exists
        $columnsFile = base_path('columns.txt');
        if (file_exists($columnsFile)) {
            @unlink($columnsFile);
        }

        $this->command->info("Successfully seeded " . count($data) . " attendance records.");
    }
}
