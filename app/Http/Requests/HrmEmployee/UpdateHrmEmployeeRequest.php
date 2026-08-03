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
use App\Models\WorkShift;

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
            'country_id'          => 'nullable|uuid|exists:global_lookups,uuid',

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
            'emergency_contact'   => 'nullable|string|max:15',

            // ── Address ───────────────────────────────────────────────────────
            'present_address'     => 'nullable|string|max:300',
            'permanent_address'   => 'nullable|string|max:300',
            'emergency_address'   => 'nullable|string|max:300',
            'address_link'        => 'nullable|string|max:300',

            // ── Employment ────────────────────────────────────────────────────
            'department_id'       => 'nullable|uuid|exists:hrm_lookups,uuid',
            'designation_id'      => 'nullable|uuid|exists:hrm_lookups,uuid',

            'employee_type_id'    => 'nullable|uuid|exists:hrm_lookups,uuid',
            'employee_category_id' => 'nullable|uuid|exists:hrm_lookups,uuid',
            'employment_type_id'  => 'nullable|uuid|exists:hrm_lookups,uuid',
            'work_shift_id'       => 'nullable|uuid|exists:work_shifts,uuid',
            'hire_date'           => 'nullable|date',
            'start_date'          => 'nullable|date',
            'employment_duration' => 'nullable|integer|min:0',
            'hired_by'            => 'nullable|uuid|exists:hrm_employees,uuid',
            'reporting_manager_id'=> 'nullable|uuid|exists:hrm_employees,uuid',
            'work_location'       => 'nullable|string|max:255',

            // ── Termination ───────────────────────────────────────────────────
            'termination_date'    => 'nullable|date',
            'terminated_by'       => 'nullable|uuid|exists:hrm_employees,uuid',
            'termination_reason'  => 'nullable|string|max:200',
            'termination_remarks' => 'nullable|string|max:200',

            // ── Passport & Nationality ───────────────────────────────────────
            'passport_no'         => 'nullable|string|max:100',
            'passport_issue'      => 'nullable|date',
            'passport_expiry'     => 'nullable|date',
            'nationality'         => 'nullable|string|max:100',

            // ── Nested Relationships ──────────────────────────────────────────
            'education'                     => 'nullable|array',
            'education.*.uuid'              => 'nullable|uuid',
            'education.*.degree_name'       => 'required|string|max:255',
            'education.*.institution'       => 'required|string|max:255',
            'education.*.passing_year'      => 'nullable|integer|min:1900|max:2100',
            'education.*.duration'          => 'nullable|string|max:100',

            'experience'                    => 'nullable|array',
            'experience.*.uuid'             => 'nullable|uuid',
            'experience.*.company_name'     => 'required|string|max:255',
            'experience.*.designation'      => 'required|string|max:255',
            'experience.*.start_date'       => 'nullable|date',
            'experience.*.end_date'         => 'nullable|date',
            'experience.*.description'      => 'nullable|string',

            'family'                        => 'nullable|array',
            'family.*.uuid'                 => 'nullable|uuid',
            'family.*.full_name'            => 'required|string|max:255',
            'family.*.date_of_birth'        => 'nullable|date',
            'family.*.gender_id'            => 'nullable|uuid|exists:global_lookups,uuid',
            'family.*.relationship_id'      => 'nullable|uuid|exists:global_lookups,uuid',
            'family.*.marital_status_id'    => 'nullable|uuid|exists:global_lookups,uuid',
            'family.*.national_id'          => 'nullable|string|max:20',
            'family.*.blood_group_id'       => 'nullable|uuid|exists:global_lookups,uuid',
            'family.*.nationality'          => 'nullable|string|max:100',

            'emergency_contacts'                            => 'nullable|array',
            'emergency_contacts.*.uuid'                     => 'nullable|uuid',
            'emergency_contacts.*.contact_name'             => 'nullable|string|max:100',
            'emergency_contacts.*.relationship_id'          => 'nullable|uuid|exists:global_lookups,uuid',
            'emergency_contacts.*.contact_number'           => 'nullable|string|max:20',
            'emergency_contacts.*.alternate_contact_number' => 'nullable|string|max:20',
            'emergency_contacts.*.address'                  => 'nullable|string|max:300',

            'documents'                     => 'nullable|array',
            'documents.*.uuid'              => 'nullable|uuid',
            'documents.*.title'             => 'required|string|max:255',
            'documents.*.doc'               => 'nullable', // file or existing path string

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
            'department_id'        => HrmLookup::class,
            'designation_id'       => HrmLookup::class,
            'employee_type_id'     => HrmLookup::class,
            'gender_id'            => GlobalLookup::class,
            'religion_id'          => GlobalLookup::class,
            'marital_status_id'    => GlobalLookup::class,
            'blood_group_id'       => GlobalLookup::class,
            'relationship_id'      => GlobalLookup::class,
            'employee_category_id' => HrmLookup::class,
            'employment_type_id'   => HrmLookup::class,
            'work_shift_id'        => WorkShift::class,
            'country_id'           => GlobalLookup::class,
            'reporting_manager_id' => HrmEmployee::class,
            'terminated_by'        => HrmEmployee::class,
            'hired_by'             => HrmEmployee::class,
        ];
    }


    /**
     * Cast FormData integer strings → int (or null if empty/"0").
     * Cast is_active string → bool.
     * This runs before validation so rules like nullable|integer|min:1 work correctly.
     */
    protected function prepareForValidation(): void
    {
        $nullableUuidFields = [
            'country_id',
            'department_id',
            'designation_id',
            'employee_type_id',
            'employee_category_id',
            'employment_type_id',
            'work_shift_id',
            'hired_by',
            'reporting_manager_id',
            'terminated_by',
            'gender_id',
            'religion_id',
            'marital_status_id',
            'blood_group_id',
        ];

        $merges = [];

        foreach ($nullableUuidFields as $field) {
            if ($this->has($field)) {
                $val = $this->input($field);
                if (is_null($val) || $val === '' || $val === 'null' || $val === 'undefined' || $val === '0' || $val === 0) {
                    $merges[$field] = null;
                }
            }
        }

        if ($this->has('employment_duration')) {
            $val = $this->input('employment_duration');
            if (is_null($val) || $val === '' || $val === 'null' || $val === 'undefined') {
                $merges['employment_duration'] = null;
            } elseif (is_numeric($val)) {
                $merges['employment_duration'] = (int) $val;
            }
        }

        if ($this->has('user_serial_no')) {
            $val = $this->input('user_serial_no');
            if (is_null($val) || $val === '' || $val === 'null' || $val === 'undefined') {
                $merges['user_serial_no'] = null;
            } elseif (is_numeric($val)) {
                $merges['user_serial_no'] = (int) $val;
            }
        }

        if ($this->has('is_active')) {
            $val = $this->input('is_active');
            $merges['is_active'] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        foreach (['family', 'education', 'experience', 'emergency_contacts'] as $arrayField) {
            if ($this->has($arrayField)) {
                $val = $this->input($arrayField);
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $merges[$arrayField] = $decoded;
                    }
                }
            }
        }

        if (!empty($merges)) {
            $this->merge($merges);
        }
    }
}
