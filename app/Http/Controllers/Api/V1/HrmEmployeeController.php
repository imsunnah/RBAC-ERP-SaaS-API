<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HrmEmployee\StoreHrmEmployeeRequest;
use App\Http\Requests\HrmEmployee\UpdateHrmEmployeeRequest;
use App\Models\HrmEmployee;
use App\Http\Resources\HrmEmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'maritalStatus'
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
            'data'    => HrmEmployeeResource::collection($employees)->response()->getData(true),
        ]);
    }

    /** Store a newly created employee. */
    public function store(StoreHrmEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', HrmEmployee::class);

        $validated = $request->validated();
        $validated = $this->handleImageUploads($request, $validated);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $employee = HrmEmployee::create($validated);

        $employee->load([
            'department',
            'designation',
            'employeeType',
            'gender',
            'bloodGroup',
            'religion',
            'maritalStatus'
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
        $validated['updated_by'] = auth()->id();

        $hrmEmployee->update($validated);

        $freshEmployee = $hrmEmployee->fresh([
            'department',
            'designation',
            'employeeType',
            'gender',
            'bloodGroup',
            'religion',
            'maritalStatus'
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
            'maritalStatus'
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
        $imageFields = ['employee_photo', 'profile_picture', 'signature_image', 'nid_image', 'business_card_image'];
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
