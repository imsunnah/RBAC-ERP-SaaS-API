<?php

namespace Database\Seeders;

use App\Models\HrmEmployee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HrmEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $total = 50000;
        $chunkSize = 1000;

        $this->command->info("Truncating hrm_employees table...");
        DB::statement('TRUNCATE TABLE hrm_employees RESTART IDENTITY CASCADE;');

        $this->command->info("Seeding $total employees...");

        for ($i = 0; $i < $total; $i += $chunkSize) {
            $data = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $count = $i + $j;
                $data[] = [
                    'uuid' => (string) Str::uuid(),
                    'company_id' => 1,
                    'organization_id' => 1,
                    'user_employee_id' => 'EMP-' . str_pad($count + 1, 7, '0', STR_PAD_LEFT),
                    'full_name' => "Employee " . ($count + 1),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('hrm_employees')->insert($data);
            $this->command->comment("Inserted " . ($i + $chunkSize) . " records...");
        }

        $this->command->info("Successfully seeded $total employees.");
    }
}
