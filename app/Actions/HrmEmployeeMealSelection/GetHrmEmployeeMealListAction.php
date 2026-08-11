<?php

namespace App\Actions\HrmEmployeeMealSelection;

use App\Models\HrmEmployeeMealSelection;
use App\Models\HrmLookup;
use Illuminate\Support\Facades\Storage;

class GetHrmEmployeeMealListAction
{
    /**
     * Execute filtering and formatting for today's meal selection lists.
     */
    public function execute(array $filters): array
    {
        try {
            $date = !empty($filters['date']) 
                ? \Carbon\Carbon::parse($filters['date'])->format('Y-m-d') 
                : now()->format('Y-m-d');
        } catch (\Exception $e) {
            $date = now()->format('Y-m-d');
        }

        $query = HrmEmployeeMealSelection::with(['employee.department', 'employee.designation', 'weeklyMeal'])
            ->where('date', $date)
            ->whereIn('selection', ['standard', 'alternative']);

        if (!empty($filters['department'])) {
            $deptId = HrmLookup::where('uuid', $filters['department'])->value('id');
            if ($deptId) {
                $query->whereHas('employee', function ($q) use ($deptId) {
                    $q->where('department_id', (string) $deptId);
                });
            }
        }

        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            
            // Pre-fetch matching department and designation IDs to avoid Postgres type mismatch on joins
            $matchingDeptIds = HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'department'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
                
            $matchingDesigIds = HrmLookup::whereHas('hrmLookupGroup', fn($q) => $q->where('slug', 'designation'))
                ->where('name', 'like', "%{$searchTerm}%")
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();

            $query->where(function ($q) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                $q->whereHas('weeklyMeal', function ($mealQ) use ($searchTerm) {
                    $mealQ->where('lunch_item', 'like', "%{$searchTerm}%")
                           ->orWhere('alternative_lunch', 'like', "%{$searchTerm}%");
                })
                ->orWhereHas('employee', function ($empQ) use ($searchTerm, $matchingDeptIds, $matchingDesigIds) {
                    $empQ->where('full_name', 'like', "%{$searchTerm}%")
                         ->orWhere('user_employee_id', 'like', "%{$searchTerm}%");
                         
                    if (!empty($matchingDesigIds)) {
                        $empQ->orWhereIn('designation_id', $matchingDesigIds);
                    }
                    if (!empty($matchingDeptIds)) {
                        $empQ->orWhereIn('department_id', $matchingDeptIds);
                    }
                });
            });
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;
        $selections = $query->paginate($perPage);

        $transformedItems = collect($selections->items())->map(function ($selection) {
            $lunchItem = '-';
            if ($selection->selection === 'standard') {
                $lunchItem = $selection->weeklyMeal?->lunch_item ?? '-';
            } elseif ($selection->selection === 'alternative') {
                $lunchItem = $selection->weeklyMeal?->alternative_lunch ?? '-';
            }

            return [
                'uuid'         => $selection->uuid,
                'employee'     => $selection->employee ? [
                    'uuid'             => $selection->employee->uuid,
                    'full_name'        => $selection->employee->full_name,
                    'user_employee_id' => $selection->employee->user_employee_id,
                    'profile_picture'  => $selection->employee->profile_picture 
                        ? Storage::url($selection->employee->profile_picture) 
                        : null,
                    'designation'      => $selection->employee->designation?->name,
                    'department'       => $selection->employee->department?->name,
                ] : null,
                'book_time'    => $selection->updated_at 
                    ? \Carbon\Carbon::parse($selection->updated_at)->format('h:i A') 
                    : null,
                'lunch_item'   => $lunchItem,
                'selection'    => $selection->selection,
                'date'         => $selection->date 
                    ? \Carbon\Carbon::parse($selection->date)->format('Y-m-d') 
                    : null,
            ];
        });

        return [
            'data'  => $transformedItems,
            'links' => [
                'first' => $selections->url(1),
                'last'  => $selections->url($selections->lastPage()),
                'prev'  => $selections->previousPageUrl(),
                'next'  => $selections->nextPageUrl(),
            ],
            'meta'  => [
                'current_page' => $selections->currentPage(),
                'last_page'    => $selections->lastPage(),
                'per_page'     => $selections->perPage(),
                'total'        => $selections->total(),
            ],
        ];
    }
}
