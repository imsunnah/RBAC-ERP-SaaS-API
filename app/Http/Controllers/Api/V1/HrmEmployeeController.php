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
    /** Eager-load relations shared across index / store / update / show. */
    private const EMPLOYEE_RELATIONS = [
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
        'documents',
    ];

    /** Fields that contain GlobalLookup UUIDs in the family array. */
    private const FAMILY_LOOKUP_FIELDS = [
        'gender_id',
        'marital_status_id',
        'blood_group_id',
        'relationship_id',
    ];

    /** Fields that contain GlobalLookup UUIDs in the emergency_contacts array. */
    private const EMERGENCY_LOOKUP_FIELDS = [
        'relationship_id',
    ];

    /** Image fields used for uploads. */
    private const IMAGE_FIELDS = [
        'employee_photo',
        'profile_picture',
        'signature_image',
        'nid_image',
        'business_card_image',
    ];

    // ─── CRUD Methods ────────────────────────────────────────────────

    /** Display a listing of the employees. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrmEmployee::class);

        $query = HrmEmployee::with(self::EMPLOYEE_RELATIONS);

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
                    $emg = $this->resolveLookupFields($emg, self::EMERGENCY_LOOKUP_FIELDS);
                    $emp->emergencyContacts()->create($emg);
                }
            }

            if (!empty($validated['documents'])) {
                $this->storeDocuments($emp, $validated['documents']);
            }

            return $emp;
        });

        $employee->load(self::EMPLOYEE_RELATIONS);

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
            $employeeData = Arr::except($validated, ['education', 'experience', 'family', 'emergency_contacts', 'emergency_contact', 'documents']);
            $hrmEmployee->update($employeeData);

            if (isset($validated['education'])) {
                $this->syncRelation($hrmEmployee, 'education', $validated['education']);
            }

            if (isset($validated['experience'])) {
                $this->syncRelation($hrmEmployee, 'experience', $validated['experience']);
            }

            if (isset($validated['family'])) {
                $this->syncRelation($hrmEmployee, 'family', $validated['family'], self::FAMILY_LOOKUP_FIELDS);
            }

            if (isset($validated['emergency_contacts'])) {
                $this->syncRelation($hrmEmployee, 'emergencyContacts', $validated['emergency_contacts'], self::EMERGENCY_LOOKUP_FIELDS);
            }

            if (isset($validated['documents'])) {
                $this->syncDocuments($hrmEmployee, $validated['documents']);
            }
        });

        $freshEmployee = $hrmEmployee->fresh(self::EMPLOYEE_RELATIONS);

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

        $hrmEmployee->load(self::EMPLOYEE_RELATIONS);

        return response()->json([
            'success' => true,
            'data'    => new HrmEmployeeResource($hrmEmployee),
        ]);
    }

    /** Remove the specified employee. */
    public function destroy(HrmEmployee $hrmEmployee): JsonResponse
    {
        $this->authorize('delete', $hrmEmployee);

        // Delete image files from storage
        foreach (self::IMAGE_FIELDS as $field) {
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

    // ─── Private Helpers ─────────────────────────────────────────────

    /**
     * Sync a hasMany relation using update-or-create logic.
     *
     * - Items with a matching `uuid` → update in place.
     * - Items without a `uuid` (or unrecognised uuid) → create as new.
     * - Existing DB records not present in the submitted array → deleted.
     *
     * @param  HrmEmployee  $employee
     * @param  string       $relation       Relation method name (e.g. 'education', 'family')
     * @param  mixed        $items          Array or JSON-string of items
     * @param  array        $lookupFields   GlobalLookup UUID fields to resolve (optional)
     */
    private function syncRelation(HrmEmployee $employee, string $relation, mixed $items, array $lookupFields = []): void
    {
        $data = is_string($items) ? json_decode($items, true) : $items;

        if (!is_array($data)) {
            return;
        }

        $keptUuids = [];

        foreach ($data as $item) {
            $uuid     = $item['uuid'] ?? null;
            $itemData = Arr::except($item, ['uuid', 'id']);
            $itemData = $this->resolveLookupFields($itemData, $lookupFields);

            if ($uuid) {
                $record = $employee->{$relation}()->where('uuid', $uuid)->first();
                if ($record) {
                    $record->update($itemData);
                    $keptUuids[] = $record->uuid;
                    continue;
                }
            }

            $newRecord = $employee->{$relation}()->create($itemData);
            $keptUuids[] = $newRecord->uuid;
        }

        // Remove records that were not in the submitted payload
        $employee->{$relation}()->whereNotIn('uuid', $keptUuids)->delete();
    }

    /**
     * Resolve GlobalLookup UUID strings to integer IDs for the given fields.
     */
    private function resolveLookupFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = GlobalLookup::resolveId($data[$field]);
            }
        }

        return $data;
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
        foreach (self::IMAGE_FIELDS as $field) {
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

    /**
     * Store document files during employee creation.
     *
     * Each item has a 'title' (string) and 'doc' (UploadedFile).
     */
    private function storeDocuments(HrmEmployee $employee, array $documents): void
    {
        foreach ($documents as $doc) {
            $filePath = null;
            if (isset($doc['doc']) && $doc['doc'] instanceof \Illuminate\Http\UploadedFile && $doc['doc']->isValid()) {
                $filePath = $doc['doc']->store('hrm/employees/documents', 'public');
            } elseif (isset($doc['doc']) && is_string($doc['doc'])) {
                $filePath = $doc['doc'];
            }

            $employee->documents()->create([
                'title' => $doc['title'] ?? 'Untitled',
                'doc'   => $filePath,
            ]);
        }
    }

    /**
     * Sync documents during employee update.
     *
     * - Items with a matching uuid → update title and optionally replace the file.
     * - Items without a uuid → create new.
     * - Existing records not in the payload → delete (and remove files from disk).
     */
    private function syncDocuments(HrmEmployee $employee, array $documents): void
    {
        $keptUuids = [];

        foreach ($documents as $doc) {
            $uuid  = $doc['uuid'] ?? null;
            $title = $doc['title'] ?? 'Untitled';

            // Handle file: could be UploadedFile, existing path string, or null
            $filePath = null;
            $hasNewFile = isset($doc['doc']) && $doc['doc'] instanceof \Illuminate\Http\UploadedFile && $doc['doc']->isValid();

            if ($uuid) {
                $record = $employee->documents()->where('uuid', $uuid)->first();
                if ($record) {
                    $updateData = ['title' => $title];

                    if ($hasNewFile) {
                        // Delete old file and store new one
                        if ($record->doc) {
                            Storage::disk('public')->delete($record->doc);
                        }
                        $updateData['doc'] = $doc['doc']->store('hrm/employees/documents', 'public');
                    }
                    // If no new file sent, keep existing doc path unchanged

                    $record->update($updateData);
                    $keptUuids[] = $record->uuid;
                    continue;
                }
            }

            // New record
            if ($hasNewFile) {
                $filePath = $doc['doc']->store('hrm/employees/documents', 'public');
            } elseif (isset($doc['doc']) && is_string($doc['doc'])) {
                $filePath = $doc['doc'];
            }

            $newRecord = $employee->documents()->create([
                'title' => $title,
                'doc'   => $filePath,
            ]);
            $keptUuids[] = $newRecord->uuid;
        }

        // Delete orphaned documents and their files
        $orphans = $employee->documents()->whereNotIn('uuid', $keptUuids)->get();
        foreach ($orphans as $orphan) {
            if ($orphan->doc) {
                Storage::disk('public')->delete($orphan->doc);
            }
            $orphan->delete();
        }
    }
}
