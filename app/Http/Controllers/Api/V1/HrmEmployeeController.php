<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmEmployee\StoreHrmEmployeeRequest;
use App\Http\Requests\HrmEmployee\UpdateHrmEmployeeRequest;
use App\Models\HrmEmployee;
use App\Models\GlobalLookup;
use App\Http\Resources\HrmEmployeeResource;
use App\Http\Resources\HrmEmployeeIndexResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class HrmEmployeeController extends Controller
{
    /** Display a listing of the employees. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployee::class);

        $query = HrmEmployee::with([
            'department',
            'designation',
            'employeeType',
            'gender',
            'bloodGroup',
            'religion',
            'maritalStatus',
            'reportingManager',
            'education',
            'experience',
            'family',
            'family.gender',
            'family.relationship',
            'family.maritalStatus',
            'family.bloodGroup',
            'workShift',
            'emergencyContacts',
            'emergencyContacts.relationship',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('user_employee_id', 'like', "%{$search}%")
                    ->orWhere('office_email', 'like', "%{$search}%")
                    ->orWhere('office_mobile_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('name')) {
            $query->where('full_name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('email')) {
            $query->where('office_email', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('status')) {
            $status = filter_var($request->status, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $status);
        }

        $query->orderBy('user_employee_id', 'asc');

        $limit = min((int) $request->input('limit', $request->input('per_page', 15)), 50000);

        if ($limit > 1000) {
            ini_set('memory_limit', '512M');
            set_time_limit(120);
        }

        $employees = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => HrmEmployeeIndexResource::collection($employees)->response()->getData(true),
        ]);
    }

    /** Store a newly created employee. */
    public function store(StoreHrmEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', HrmEmployee::class);

        $validated = $request->validated();
        $validated = $this->handleImageUploads($request, $validated);

        $validated['created_by'] = Auth::user()->id;
        $validated['updated_by'] = Auth::user()->id;

        $employee = DB::transaction(function () use ($validated) {
            $employeeData = Arr::except($validated, ['education', 'experience', 'family', 'emergency_contacts', 'emergency_contact']);
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
                    if (!empty($fam['gender_id'])) {
                        $fam['gender_id'] = GlobalLookup::resolveId($fam['gender_id']);
                    }
                    if (!empty($fam['marital_status_id'])) {
                        $fam['marital_status_id'] = GlobalLookup::resolveId($fam['marital_status_id']);
                    }
                    if (!empty($fam['blood_group_id'])) {
                        $fam['blood_group_id'] = GlobalLookup::resolveId($fam['blood_group_id']);
                    }
                    if (!empty($fam['relationship_id'])) {
                        $fam['relationship_id'] = GlobalLookup::resolveId($fam['relationship_id']);
                    }
                    $emp->family()->create($fam);
                }
            }

            if (!empty($validated['emergency_contacts'])) {
                foreach ($validated['emergency_contacts'] as $emg) {
                    if (!empty($emg['relationship_id'])) {
                        $emg['relationship_id'] = GlobalLookup::resolveId($emg['relationship_id']);
                    }
                    $emp->emergencyContacts()->create($emg);
                }
            } elseif (!empty($validated['emergency_contact']) && is_array($validated['emergency_contact'])) {
                $emg = $validated['emergency_contact'];
                if (!empty($emg['relationship_id'])) {
                    $emg['relationship_id'] = GlobalLookup::resolveId($emg['relationship_id']);
                }
                $emp->emergencyContacts()->create($emg);
            }

            return $emp;
        });

        $employee->load([
            'department',
            'designation',
            'employeeType',
            'gender',
            'bloodGroup',
            'religion',
            'maritalStatus',
            'reportingManager',
            'education',
            'experience',
            'family',
            'family.gender',
            'family.relationship',
            'family.maritalStatus',
            'family.bloodGroup',
            'workShift',
            'emergencyContacts',
            'emergencyContacts.relationship',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data'    => new HrmEmployeeResource($employee),
        ], 201);
    }

    /** Update the specified employee. */
    public function update(UpdateHrmEmployeeRequest $request, HrmEmployee $hrmEmployee): JsonResponse
    {
        $this->authorize('update', $hrmEmployee);

        $validated = $request->validated();
        $validated = $this->handleImageUploads($request, $validated, $hrmEmployee);
        $validated['updated_by'] = Auth::user()->id;

        DB::transaction(function () use ($hrmEmployee, $validated) {
            $employeeData = Arr::except($validated, ['education', 'experience', 'family', 'emergency_contacts', 'emergency_contact']);
            $hrmEmployee->update($employeeData);

            if (isset($validated['education'])) {
                $hrmEmployee->education()->delete();
                foreach ($validated['education'] as $edu) {
                    $hrmEmployee->education()->create($edu);
                }
            }

            if (isset($validated['experience'])) {
                $hrmEmployee->experience()->delete();
                foreach ($validated['experience'] as $exp) {
                    $hrmEmployee->experience()->create($exp);
                }
            }

            if (isset($validated['family'])) {
                $hrmEmployee->family()->delete();
                foreach ($validated['family'] as $fam) {
                    if (!empty($fam['gender_id'])) {
                        $fam['gender_id'] = GlobalLookup::resolveId($fam['gender_id']);
                    }
                    if (!empty($fam['marital_status_id'])) {
                        $fam['marital_status_id'] = GlobalLookup::resolveId($fam['marital_status_id']);
                    }
                    if (!empty($fam['blood_group_id'])) {
                        $fam['blood_group_id'] = GlobalLookup::resolveId($fam['blood_group_id']);
                    }
                    if (!empty($fam['relationship_id'])) {
                        $fam['relationship_id'] = GlobalLookup::resolveId($fam['relationship_id']);
                    }
                    $hrmEmployee->family()->create($fam);
                }
            }

            if (isset($validated['emergency_contacts'])) {
                $hrmEmployee->emergencyContacts()->delete();
                foreach ($validated['emergency_contacts'] as $emg) {
                    if (!empty($emg['relationship_id'])) {
                        $emg['relationship_id'] = GlobalLookup::resolveId($emg['relationship_id']);
                    }
                    $hrmEmployee->emergencyContacts()->create($emg);
                }
            } elseif (isset($validated['emergency_contact']) && is_array($validated['emergency_contact'])) {
                $hrmEmployee->emergencyContacts()->delete();
                $emg = $validated['emergency_contact'];
                if (!empty($emg['relationship_id'])) {
                    $emg['relationship_id'] = GlobalLookup::resolveId($emg['relationship_id']);
                }
                $hrmEmployee->emergencyContacts()->create($emg);
            }
        });

        $freshEmployee = $hrmEmployee->fresh([
            'department',
            'designation',
            'employeeType',
            'gender',
            'bloodGroup',
            'religion',
            'maritalStatus',
            'reportingManager',
            'education',
            'experience',
            'family',
            'family.gender',
            'family.relationship',
            'family.maritalStatus',
            'family.bloodGroup',
            'workShift',
            'emergencyContacts',
            'emergencyContacts.relationship',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data'    => new HrmEmployeeResource($freshEmployee),
        ]);
    }

    /** Display the specified employee. */
    public function show(HrmEmployee $hrmEmployee): JsonResponse
    {
        $this->authorize('view', $hrmEmployee);

        $hrmEmployee->load([
            'department',
            'designation',
            'employeeType',
            'gender',
            'bloodGroup',
            'religion',
            'maritalStatus',
            'reportingManager',
            'education',
            'experience',
            'family',
            'family.gender',
            'family.relationship',
            'family.maritalStatus',
            'family.bloodGroup',
            'workShift',
            'emergencyContacts',
            'emergencyContacts.relationship',
        ]);

        return response()->json([
            'success' => true,
            'data'    => new HrmEmployeeResource($hrmEmployee),
        ]);
    }


    /** Remove the specified employee. */
    public function destroy(HrmEmployee $hrmEmployee): JsonResponse
    {
        $this->authorize('delete', $hrmEmployee);

        // Optionally delete image files from storage
        $imageFields = ['employe
        e_photo', 'profile_picture', 'signature_image', 'nid_image', 'business_card_image'];
        foreach ($imageFields as $field) {
            if ($hrmEmployee->{$field}) {
                Storage::disk('public')->delete($hrmEmployee->{$field});
            }
        }



        $hrmEmployee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }

    /**
     * Handle image uploads for all image fields.
     *
     * Rules:
     * - If the request has a file for the field → store it, replace path in $validated.
     * - If the request has a non-empty string for the field → it's the existing path, keep it.
     * - If the field is absent from the request entirely → for UPDATE, unset from $validated
     *   so Eloquent does NOT overwrite the existing DB value.
     * - For CREATE with no file → set null.
     */
    private function handleImageUploads(Request $request, array $validated, ?HrmEmployee $employee = null): array
    {
        $imageFields = [
            'employee_photo',
            'profile_picture',
            'signature_image',
            'nid_image',
            'business_card_image',
        ];

        foreach ($imageFields as $field) {
            if ($request->hasFile($field) && $request->file($field)->isValid()) {
                // Delete old file on update
                if ($employee && $employee->$field) {
                    Storage::delete($employee->$field);
                }
                $path = $request->file($field)->store("hrm/employees/{$field}", 'public');
                $validated[$field] = $path;
            } elseif (array_key_exists($field, $validated) && is_string($validated[$field])) {
                // Strip the /storage/ prefix if the frontend echoed back the full URL
                $val = $validated[$field];
                if (str_starts_with($val, '/storage/')) {
                    $validated[$field] = substr($val, strlen('/storage/'));
                }
                // else: keep whatever string path came back as-is
            } else {
                // Not submitted — don't overwrite existing DB value
                unset($validated[$field]);
            }
        }

        return $validated;
    }
}
