<?php

namespace App\Http\Requests\HrmEmployee;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\HrmEmployee;

class UpdateHrmEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Laravel's implicit model binding binds HrmEmployee as 'hrmEmployee' (camelCase).
        // $this->route('hrmEmployee') gives us the already-resolved model instance.
        $employee = $this->route('hrmEmployee');

        // Resolve to the integer primary key for the unique-except rule
        if ($employee instanceof HrmEmployee) {
            $employeeId = $employee->id;
        } elseif (is_string($employee) || is_numeric($employee)) {
            // Fallback: look up by UUID or ID
            $employeeId = HrmEmployee::where('uuid', $employee)->value('id')
                ?? HrmEmployee::find($employee)?->id;
        } else {
            $employeeId = null;
        }

        return [
            // ── Identification ────────────────────────────────────────────────
            'user_employee_id'    => 'nullable|string|max:16|unique:hrm_employees,user_employee_id,' . $employeeId,
            'biometric_id'        => 'nullable|string|max:16',
            'national_id'         => 'nullable|string|max:20',

            // ── Personal ──────────────────────────────────────────────────────
            'full_name'           => 'sometimes|required|string|max:100',
            'employee_name_local' => 'nullable|string|max:300',
            'date_of_birth'       => 'nullable|date',
            'gender_id'           => 'nullable|string|max:3',
            'blood_group_id'      => 'nullable|string|max:3',
            'religion_id'         => 'nullable|integer|min:1',
            'marital_status_id'   => 'nullable|integer|min:1',
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
            'department_id'       => 'nullable|string|max:14',
            'designation_id'      => 'nullable|integer|min:1',
            'employee_type_id'    => 'nullable|integer|min:1',
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
     * Cast FormData integer strings → int (or null if empty/"0").
     * Cast is_active string → bool.
     * This runs before validation so rules like nullable|integer|min:1 work correctly.
     */
    protected function prepareForValidation(): void
    {
        $intFields = [
            'religion_id',
            'marital_status_id',
            'country_id',
            'designation_id',
            'employee_type_id',
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
