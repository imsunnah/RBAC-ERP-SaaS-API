<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\MetadataResource;

class HrmEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'user_employee_id'    => $this->user_employee_id,
            'biometric_id'        => $this->biometric_id,
            'national_id'         => $this->national_id,

            // ── Personal ──────────────────────────────────────────────────────
            'full_name'           => $this->full_name,
            'employee_name_local' => $this->employee_name_local,
            'date_of_birth'       => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'gender_id'           => $this->gender_id,
            'blood_group_id'      => $this->blood_group_id,
            'religion_id'         => $this->religion_id ? (int) $this->religion_id : null,
            'marital_status_id'   => $this->marital_status_id ? (int) $this->marital_status_id : null,
            'country_id'          => $this->country_id ? (int) $this->country_id : null,
            'weight_kg'           => $this->weight_kg ? (float) $this->weight_kg : null,
            'height_cm'           => $this->height_cm ? (float) $this->height_cm : null,
            'eye_color'           => $this->eye_color,

            // ── Contact ───────────────────────────────────────────────────────
            'office_email'        => $this->office_email,
            'personal_email'      => $this->personal_email,
            'alternate_email'     => $this->alternate_email,
            'office_mobile_no'    => $this->office_mobile_no,
            'personal_mobile_no'  => $this->personal_mobile_no,
            'secondary_mobile_no' => $this->secondary_mobile_no,
            'tertiary_mobile_no'  => $this->tertiary_mobile_no,
            'emergency_contact'   => $this->emergency_contact,

            // ── Address ───────────────────────────────────────────────────────
            'present_address'     => $this->present_address,
            'permanent_address'   => $this->permanent_address,
            'emergency_address'   => $this->emergency_address,
            'address_link'        => $this->address_link,

            // ── Employment ────────────────────────────────────────────────────
            'department'           => new MetadataResource($this->whenLoaded('department')),
            'designation'          => new MetadataResource($this->whenLoaded('designation')),
            // 'department_id'        => $this->department_id,
            // 'designation_id'       => $this->designation_id,
            'employee_type_id'     => $this->employee_type_id ? (int) $this->employee_type_id : null,
            'employee_category_id' => $this->employee_category_id ? (int) $this->employee_category_id : null,
            'employment_type_id'   => $this->employment_type_id ? (int) $this->employment_type_id : null,
            'work_shift_id'        => $this->work_shift_id ? (int) $this->work_shift_id : null,
            'hire_date'            => $this->hire_date ? $this->hire_date->format('Y-m-d') : null,
            'start_date'           => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'employment_duration'  => $this->employment_duration ? (int) $this->employment_duration : null,
            'hired_by'             => $this->hired_by ? (int) $this->hired_by : null,

            // ── Termination ───────────────────────────────────────────────────
            'termination_date'    => $this->termination_date ? $this->termination_date->format('Y-m-d') : null,
            'terminated_by'       => $this->terminated_by,
            'termination_reason'  => $this->termination_reason,
            'termination_remarks' => $this->termination_remarks,

            // ── Images / Files ────────────────────────────────────────────────
            'employee_photo'      => $this->employee_photo ? Storage::url($this->employee_photo) : null,
            'profile_picture'     => $this->profile_picture ? Storage::url($this->profile_picture) : null,
            'signature_image'     => $this->signature_image ? Storage::url($this->signature_image) : null,
            'nid_image'           => $this->nid_image ? Storage::url($this->nid_image) : null,
            'business_card_image' => $this->business_card_image ? Storage::url($this->business_card_image) : null,

            // ── System ────────────────────────────────────────────────────────
            'user_serial_no'      => $this->user_serial_no ? (int) $this->user_serial_no : null,
            'is_active'           => (bool) $this->is_active,
            'created_at'          => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'          => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
