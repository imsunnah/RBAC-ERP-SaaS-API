<?php

namespace App\Services;

use App\Models\DutyRoster;
use App\Models\Employee;
use App\Models\PayrollConfig;
use App\Models\WorkShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /**
     * List all shifts (paginated).
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return WorkShift::orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new shift.
     */
    public function create(array $data): WorkShift
    {
        return DB::transaction(function () use ($data) {
            // If this is marked as default, unset other defaults for the org
            if (!empty($data['is_default'])) {
                WorkShift::where('org_no', $data['org_no'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return WorkShift::create($data);
        });
    }

    /**
     * Update an existing shift.
     */
    public function update(WorkShift $shift, array $data): WorkShift
    {
        return DB::transaction(function () use ($shift, $data) {
            // If setting as default, unset other defaults for the org
            if (!empty($data['is_default']) && !$shift->is_default) {
                WorkShift::where('org_no', $shift->org_no)
                    ->where('is_default', true)
                    ->where('shift_id', '!=', $shift->shift_id)
                    ->update(['is_default' => false]);
            }

            $shift->update($data);
            return $shift->fresh();
        });
    }

    /**
     * Delete a shift (only if no active rosters depend on it).
     */
    public function delete(WorkShift $shift): bool
    {
        $hasRosters = DutyRoster::where('shift_id', $shift->shift_id)
            ->where('date', '>=', now()->toDateString())
            ->exists();

        if ($hasRosters) {
            throw new \Exception('Cannot delete shift with active roster assignments.');
        }

        return $shift->delete();
    }

    // ─── Roster Management ────────────────────────────────

    /**
     * List roster entries (paginated, filterable).
     */
    public function listRosters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = DutyRoster::with(['employee:id,employee_code,first_name,last_name', 'shift:shift_id,name,short_code,start_time,end_time'])
            ->orderByDesc('date');

        if (!empty($filters['employee_id'])) {
            $query->forEmployee($filters['employee_id']);
        }

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->dateRange($filters['from_date'], $filters['to_date']);
        }

        if (!empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create or update a single roster entry.
     */
    public function createRoster(array $data): DutyRoster
    {
        // Always resolve org_no from the employee
        if (empty($data['org_no'])) {
            $data['org_no'] = Employee::where('id', $data['employee_id'])->value('org_no');
        }

        return DutyRoster::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            [
                'shift_id'   => $data['shift_id'],
                'is_off_day' => $data['is_off_day'] ?? false,
                'remarks'    => $data['remarks'] ?? null,
                'org_no'     => $data['org_no'],
                'created_by' => $data['created_by'] ?? null,
            ]
        );
    }

    /**
     * Bulk assign shifts: multiple employees × date range.
     */
    public function bulkCreateRosters(array $data): int
    {
        $employeeIds = $data['employee_ids'];
        $shiftId = $data['shift_id'];
        $startDate = \Carbon\Carbon::parse($data['start_date']);
        $endDate = \Carbon\Carbon::parse($data['end_date']);
        $isOffDay = $data['is_off_day'] ?? false;
        $createdBy = $data['created_by'];

        // Pre-load org_no per employee
        $employeeOrgs = Employee::whereIn('id', $employeeIds)
            ->pluck('org_no', 'id');

        $count = 0;

        DB::transaction(function () use ($employeeIds, $shiftId, $startDate, $endDate, $isOffDay, $employeeOrgs, $createdBy, &$count) {
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $dateStr = $current->toDateString();

                foreach ($employeeIds as $employeeId) {
                    // Use updateOrCreate to handle existing entries gracefully
                    DutyRoster::updateOrCreate(
                        ['employee_id' => $employeeId, 'date' => $dateStr],
                        [
                            'shift_id' => $shiftId,
                            'is_off_day' => $isOffDay,
                            'org_no' => $employeeOrgs[$employeeId] ?? null,
                            'created_by' => $createdBy,
                        ]
                    );
                    $count++;
                }

                $current->addDay();
            }
        });

        return $count;
    }

    /**
     * Delete a roster entry.
     */
    public function deleteRoster(DutyRoster $roster): bool
    {
        return $roster->delete();
    }

    // ─── Shift Resolution ─────────────────────────────────

    /**
     * Get the effective shift for an employee on a given date.
     * Resolution chain: roster → org default shift → payroll_configs fallback.
     *
     * Returns array with shift parameters used by attendance calculations.
     */
    public function getEmployeeShift(int $employeeId, string $date, ?int $orgNo = null): array
    {
        // 1. Check roster assignment
        $roster = DutyRoster::with('shift')
            ->where('employee_id', $employeeId)
            ->where('date', $date)
            ->first();

        if ($roster && $roster->shift) {
            return [
                'source' => 'roster',
                'shift_id' => $roster->shift->shift_id,
                'name' => $roster->shift->name,
                'start_time' => $roster->shift->start_time,
                'end_time' => $roster->shift->end_time,
                'is_overnight' => $roster->shift->is_overnight,
                'break_duration_minutes' => $roster->shift->break_duration_minutes,
                'expected_hours' => (float) $roster->shift->expected_hours,
                'grace_minutes' => $roster->shift->grace_minutes,
                'is_off_day' => $roster->is_off_day,
            ];
        }

        // 2. Org default shift
        $defaultShift = WorkShift::active()->default()->first();

        if ($defaultShift) {
            return [
                'source' => 'default_shift',
                'shift_id' => $defaultShift->shift_id,
                'name' => $defaultShift->name,
                'start_time' => $defaultShift->start_time,
                'end_time' => $defaultShift->end_time,
                'is_overnight' => $defaultShift->is_overnight,
                'break_duration_minutes' => $defaultShift->break_duration_minutes,
                'expected_hours' => (float) $defaultShift->expected_hours,
                'grace_minutes' => $defaultShift->grace_minutes,
                'is_off_day' => false,
            ];
        }

        // 3. Fallback to payroll_configs (use employee's org if provided)
        $configQuery = fn(string $key) => $orgNo
            ? PayrollConfig::byKey($key)->where('org_no', $orgNo)->first()
            : PayrollConfig::byKey($key)->first();

        $startTime = $configQuery('office_start_time')?->typed_value ?? '09:00';
        $endTime = $configQuery('office_end_time')?->typed_value ?? '17:00';
        $breakMins = (int) ($configQuery('break_duration_minutes')?->typed_value ?? 60);
        $graceMins = (int) ($configQuery('grace_minutes')?->typed_value ?? 15);
        $standardHours = (float) ($configQuery('standard_daily_hours')?->typed_value ?? 8);

        return [
            'source' => 'config_fallback',
            'shift_id' => null,
            'name' => 'General',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_overnight' => false,
            'break_duration_minutes' => $breakMins,
            'expected_hours' => $standardHours,
            'grace_minutes' => $graceMins,
            'is_off_day' => false,
        ];
    }
}
