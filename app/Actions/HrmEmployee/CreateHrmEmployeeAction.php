<?php

namespace App\Actions\HrmEmployee;

use App\Actions\HrmEmployee\Concerns\HandlesEmployeeMedia;
use App\Models\HrmEmployee;
use App\Models\HrmLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateHrmEmployeeAction
{
    use HandlesEmployeeMedia;

    /**
     * Execute the action to create a new employee.
     */
    public function execute(array $validated, Request $request, array $relations = []): HrmEmployee
    {
        $validated = $this->handleImageUploads($request, $validated);

        $currentOrg = app('current_organization');
        if ($currentOrg) {
            $validated['organization_id'] = $validated['organization_id'] ?? $currentOrg->id;
            $validated['company_id']      = $validated['company_id'] ?? $currentOrg->company_id;
        }

        $userId = Auth::id();
        $validated['created_by'] = $userId;
        $validated['updated_by'] = $userId;

        $employee = DB::transaction(function () use ($validated) {
            $employeeData = Arr::except($validated, ['education', 'experience', 'family', 'emergency_contacts', 'emergency_contact', 'documents']);
            $emp = HrmEmployee::create($employeeData);

            if (!empty($validated['education'])) {
                foreach ($validated['education'] as $edu) {
                    $emp->education()->create($edu);
                }
            }

            if (!empty($validated['experience'])) {
                foreach ($validated['experience'] as $exp) {
                    $emp->experience()->create($exp);
                }
            }

            if (!empty($validated['family'])) {
                foreach ($validated['family'] as $fam) {
                    $fam = $this->resolveLookupFields($fam, self::FAMILY_LOOKUP_FIELDS);
                    $emp->family()->create($fam);
                }
            }

            if (!empty($validated['emergency_contacts'])) {
                foreach ($validated['emergency_contacts'] as $emg) {
                    $emg = $this->resolveLookupFields($emg, self::EMERGENCY_LOOKUP_FIELDS, HrmLookup::class);
                    $emp->emergencyContacts()->create($emg);
                }
            }

            if (!empty($validated['documents'])) {
                $this->storeDocuments($emp, $validated['documents']);
            }

            return $emp;
        });

        if (!empty($relations)) {
            $employee->load($relations);
        }

        return $employee;
    }
}
