<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HrmEmployee;
use App\Models\HrmWeeklyMeal;
use App\Models\HrmEmployeeMealSelection;
use App\Models\HrmHoliday;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class AutoSelectDefaultMeals extends Command
{
    protected $signature = 'hrm:auto-select-meals';
    protected $description = 'Automatically selects the standard lunch item for active employees who have not manually chosen their option for the upcoming week, skipping holidays.';

    public function handle()
    {
        $this->info('Starting auto-selection of default meals...');

        // Disable query logging to prevent memory leaks during large batch inserts
        DB::disableQueryLog();

        // Auto select meals for the next 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::today()->addDays($i);
            $dayName = strtolower($date->format('l')); // e.g. monday

            // Fetch active weekly meals
            $weeklyMeals = HrmWeeklyMeal::where('is_active', true)
                ->whereRaw('LOWER(day) = ?', [$dayName])
                ->get();

            foreach ($weeklyMeals as $meal) {
                // Check if this date is a registered active holiday for this organization
                $isHoliday = HrmHoliday::where('date', $date->format('Y-m-d'))
                    ->where('is_active', true)
                    ->where('organization_id', $meal->organization_id)
                    ->exists();

                if ($isHoliday) {
                    $this->info("Skipping {$date->format('Y-m-d')} as it is an active holiday.");
                    continue;
                }

                $query = HrmEmployee::where('is_active', true);
                if ($meal->organization_id) {
                    $query->where('organization_id', $meal->organization_id);
                }

                // Chunk employees to prevent loading too many models at once
                $query->chunk(200, function ($employees) use ($date, $meal) {
                    $employeeIds = $employees->pluck('id')->toArray();

                    // Pre-fetch all selections for this chunk of employees to avoid N+1 query loop
                    $existingSelections = HrmEmployeeMealSelection::whereIn('hrm_employee_id', $employeeIds)
                        ->where('date', $date->format('Y-m-d'))
                        ->get()
                        ->keyBy('hrm_employee_id');

                    $insertData = [];
                    $now = now();

                    foreach ($employees as $employee) {
                        $selection = $existingSelections->get($employee->id);

                        if (!$selection) {
                            $insertData[] = [
                                'uuid'                => (string) \Illuminate\Support\Str::uuid(),
                                'company_id'          => $meal->company_id,
                                'organization_id'     => $meal->organization_id,
                                'hrm_employee_id'    => $employee->id,
                                'hrm_weekly_meal_id' => $meal->id,
                                'date'                => $date->format('Y-m-d'),
                                'selection'           => 'standard',
                                'is_manually_updated' => false,
                                'created_at'          => $now,
                                'updated_at'          => $now,
                            ];
                        } elseif (!$selection->is_manually_updated) {
                            $selection->update([
                                'hrm_weekly_meal_id' => $meal->id,
                                'selection'           => 'standard',
                            ]);
                        }
                    }

                    // Bulk insert new default selections
                    if (!empty($insertData)) {
                        HrmEmployeeMealSelection::insert($insertData);
                    }
                });
            }
        }

        $this->info('Default meal auto-selection completed successfully!');
        return Command::SUCCESS;
    }
}
