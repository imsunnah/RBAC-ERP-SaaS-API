<?php

namespace App\Http\Requests\HrmEmployee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('hrm_employee') ? $this->route('hrm_employee')->id : null;

        return [
            'user_employee_id' => 'nullable|string|max:16|unique:hrm_employees,user_employee_id,' . $employeeId,
            'biometric_id' => 'nullable|string|max:16',
            'national_id' => 'nullable|string|max:20',
            'full_name' => 'sometimes|required|string|max:100',
            'employee_name_local' => 'nullable|string|max:300',
            'date_of_birth' => 'nullable|date',
            'gender_id' => 'nullable|string|max:3',
            'blood_group_id' => 'nullable|string|max:3',
            'religion_id' => 'nullable|integer',
            'marital_status_id' => 'nullable|integer',
            'country_id' => 'nullable|integer',
            'weight_kg' => 'nullable|numeric|min:0|max:999.99',
            'height_cm' => 'nullable|numeric|min:0|max:999.99',
            'eye_color' => 'nullable|string|max:100',
            
            'office_email' => 'nullable|email|max:100',
            'personal_email' => 'nullable|email|max:100',
            'alternate_email' => 'nullable|email|max:100',
            'office_mobile_no' => 'nullable|string|max:15',
            'personal_mobile_no' => 'nullable|string|max:15',
            'secondary_mobile_no' => 'nullable|string|max:15',
            'tertiary_mobile_no' => 'nullable|string|max:15',
            'emergency_contact' => 'nullable|string|max:6',
            'present_address' => 'nullable|string|max:300',
            'permanent_address' => 'nullable|string|max:300',
            'emergency_address' => 'nullable|string|max:300',
            'address_link' => 'nullable|string|max:300',
            
            'department_id' => 'nullable|string|max:14',
            'designation_id' => 'nullable|integer',
            'employee_type_id' => 'nullable|integer',
            'employee_category_id' => 'nullable|integer',
            'employment_type_id' => 'nullable|integer',
            'work_shift_id' => 'nullable|integer',
            
            'hire_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'employment_duration' => 'nullable|integer',
            'hired_by' => 'nullable|integer',
            
            'termination_date' => 'nullable|date',
            'terminated_by' => 'nullable|string|max:20',
            'termination_reason' => 'nullable|string|max:200',
            'termination_remarks' => 'nullable|string|max:200',
            
            'employee_photo' => 'nullable|string|max:200',
            'profile_picture' => 'nullable|string|max:200',
            'signature_image' => 'nullable|string|max:200',
            'nid_image' => 'nullable|string|max:200',
            'business_card_image' => 'nullable|string|max:200',
            
            'user_serial_no' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ];
    }
}
