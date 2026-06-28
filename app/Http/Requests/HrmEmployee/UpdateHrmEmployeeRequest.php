<?php

namespace App\Http\Requests\HrmEmployee;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\HrmEmployee;
use App\Http\Requests\Traits\ResolvesUuidToId;
use App\Models\HrmLookup;
use App\Models\Gender;
use App\Models\GlobalLookup;
use App\Models\Religion;
use App\Models\MaritalStatus;

use Illuminate\Validation\Rule;

class UpdateHrmEmployeeRequest extends FormRequest
{
    use ResolvesUuidToId;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 1. Try camelCase (Laravel's standard default binding)
        // 2. Try snake_case (Commonly used in API resource routes)
        // 3. Fallback to extracting from the request payload if available
        $employee = $this->route('hrmEmployee')
            ?? $this->route('hrm_employee')
            ?? $this->route('employee');

        // Extract the primary key ID safely
        if ($employee instanceof HrmEmployee) {
            $employeeId = $employee->id;
        } elseif (is_numeric($employee)) {
            $employeeId = $employee;
        } elseif (is_string($employee) && !empty($employee)) {
            // Fallback: lookup using UUID or key identifier if it's passed as a string slug
            $employeeId = HrmEmployee::where('uuid', $employee)->value('id')
                ?? HrmEmployee::where('id', $employee)->value('id');
        } else {
            $employeeId = null;
        }

        return [
            // ── Identification ────────────────────────────────────────────────
            'user_employee_id'    => [
                'nullable',
                'string',
                'max:16',
                // This safely ignores the current employee row during the unique check
                Rule::unique('hrm_employees', 'user_employee_id')->ignore($employeeId),
            ],
            'biometric_id'        => 'nullable|string|max:16',
            'national_id'         => 'nullable|string|max:20',

            // ── Personal ──────────────────────────────────────────────────────
            'full_name'           => 'sometimes|required|string|max:100',
            'employee_name_local' => 'nullable|string|max:300',
            'date_of_birth'       => 'nullable|date',
            'gender_id'           => 'nullable|uuid|exists:global_lookups,uuid',
            'blood_group_id'      => 'nullable|uuid|exists:global_lookups,uuid',
            'religion_id'         => 'nullable|uuid|exists:global_lookups,uuid',
            'marital_status_id'   => 'nullable|uuid|exists:global_lookups,uuid',
            'country_id'          => 'nullable|integer|min:1',

            'weight_kg'           => 'nullable|numeric|min:0|max:999.99',
            'height_cm'           => 'nullable|numeric|min:0|max:999.99',
            'eye_color'           => 'nullable|string|max:100',

            // ── Contact ───────────────────────────────────────────────────────
            'office_email'        => 'nullable|email|max:100',
            'personal_email'      => 'nullable|email|max:100',
            'alternate_email'     => 'nullable|email|max:100',
            'office_mobile_no'    => 'nullable|string|max:15',
            'personal_mobile_no'  => 'nullable|string|max:15',
            'secondary_mobile_no' => 'nullable|string|max:15',
            'tertiary_mobile_no'  => 'nullable|string|max:15',
            'emergency_contact'   => 'nullable|string|max:6',

            // ── Address ───────────────────────────────────────────────────────
            'present_address'     => 'nullable|string|max:300',
            'permanent_address'   => 'nullable|string|max:300',
            'emergency_address'   => 'nullable|string|max:300',
            'address_link'        => 'nullable|string|max:300',

            // ── Employment ────────────────────────────────────────────────────
            'department_id'       => 'nullable|uuid|exists:hrm_lookups,uuid',
            'designation_id'      => 'nullable|uuid|exists:hrm_lookups,uuid',

            'employee_type_id'    => 'nullable|uuid|exists:global_lookups,uuid',
            'employee_category_id' => 'nullable|integer|min:1',
            'employment_type_id'  => 'nullable|integer|min:1',
            'work_shift_id'       => 'nullable|integer|min:1',
            'hire_date'           => 'nullable|date',
            'start_date'          => 'nullable|date',
            'employment_duration' => 'nullable|integer|min:0',
            'hired_by'            => 'nullable|integer|min:1',

            // ── Termination ───────────────────────────────────────────────────
            'termination_date'    => 'nullable|date',
            'terminated_by'       => 'nullable|string|max:20',
            'termination_reason'  => 'nullable|string|max:200',
            'termination_remarks' => 'nullable|string|max:200',

            // ── Images ────────────────────────────────────────────────────────
            'employee_photo'      => 'nullable',
            'profile_picture'     => 'nullable',
            'signature_image'     => 'nullable',
            'nid_image'           => 'nullable',
            'business_card_image' => 'nullable',

            // ── System ────────────────────────────────────────────────────────
            'user_serial_no'      => 'nullable|integer|min:0',
            'is_active'           => 'nullable|boolean',
        ];
    }

    /**
     * Define which fields should be resolved from UUID to ID.
     */
    protected function resolvedKeys(): array
    {
        return [
            'department_id'     => HrmLookup::class,
            'designation_id'    => HrmLookup::class,
            'employee_type_id'  => GlobalLookup::class,
            'gender_id'         => GlobalLookup::class,
            'religion_id'       => GlobalLookup::class,
            'marital_status_id' => GlobalLookup::class,
            'blood_group_id' => GlobalLookup::class,
        ];
    }


    /**
     * Cast FormData integer strings → int (or null if empty/"0").
     * Cast is_active string → bool.
     * This runs before validation so rules like nullable|integer|min:1 work correctly.
     */
    protected function prepareForValidation(): void
    {
        $intFields = [
            'country_id',

            'employee_category_id',
            'employment_type_id',
            'work_shift_id',
            'employment_duration',
            'hired_by',
            'user_serial_no',
        ];

        $merges = [];

        foreach ($intFields as $field) {
            if ($this->has($field)) {
                $val = $this->input($field);
                if ($val === '' || $val === '0' || $val === 0) {
                    $merges[$field] = null;
                } else {
                    $merges[$field] = (int) $val;
                }
            }
        }

        if ($this->has('is_active')) {
            $val = $this->input('is_active');
            $merges['is_active'] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($merges)) {
            $this->merge($merges);
        }
    }
}
