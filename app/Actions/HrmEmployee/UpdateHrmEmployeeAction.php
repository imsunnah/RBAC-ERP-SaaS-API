<?php

namespace App\Actions\HrmEmployee;

use App\Actions\HrmEmployee\Concerns\HandlesEmployeeMedia;
use App\Models\HrmEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateHrmEmployeeAction
{
    use HandlesEmployeeMedia;

    /**
     * Execute the action to update an existing employee.
     */
    public function execute(HrmEmployee $employee, array $validated, Request $request, array $relations = []): HrmEmployee
    {
        $validated = $this->handleImageUploads($request, $validated, $employee);
        $validated['updated_by'] = Auth::id();

        DB::transaction(function () use ($employee, $validated) {
            $employeeData = Arr::except($validated, ['education', 'experience', 'family', 'emergency_contacts', 'emergency_contact', 'documents']);
            $employee->update($employeeData);

            if (isset($validated['education'])) {
                $this->syncRelation($employee, 'education', $validated['education']);
            }

            if (isset($validated['experience'])) {
                $this->syncRelation($employee, 'experience', $validated['experience']);
            }

            if (isset($validated['family'])) {
                $this->syncRelation($employee, 'family', $validated['family'], self::FAMILY_LOOKUP_FIELDS);
            }

            if (isset($validated['emergency_contacts'])) {
                $this->syncRelation($employee, 'emergencyContacts', $validated['emergency_contacts'], self::EMERGENCY_LOOKUP_FIELDS);
            }

            if (isset($validated['documents'])) {
                $this->syncDocuments($employee, $validated['documents']);
            }
        });

        return !empty($relations) ? $employee->fresh($relations) : $employee;
    }
}
