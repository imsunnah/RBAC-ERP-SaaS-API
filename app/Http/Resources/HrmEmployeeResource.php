<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\MetadataResource;
use Carbon\Carbon;

class HrmEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'user_employee_id'    => $this->user_employee_id,
            'biometric_id'        => $this->biometric_id,
            'national_id'         => $this->national_id,
            'passport_no'         => $this->passport_no,
            'passport_issue'      => $this->passport_issue ? $this->passport_issue->format('Y-m-d') : null,
            'passport_expiry'     => $this->passport_expiry ? $this->passport_expiry->format('Y-m-d') : null,
            'nationality'         => $this->nationality,

            // ── Personal ──────────────────────────────────────────────────────
            'full_name'           => $this->full_name,
            'employee_name_local' => $this->employee_name_local,
            'date_of_birth'       => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'gender'              => new MetadataResource($this->whenLoaded('gender')),
            'blood_group'         => new MetadataResource($this->whenLoaded('bloodGroup')),
            'religion'            => new MetadataResource($this->whenLoaded('religion')),
            'marital_status_id'   => $this->maritalStatus?->uuid,
            'marital_status'      => new MetadataResource($this->whenLoaded('maritalStatus')),
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
            'employee_type'        => new MetadataResource($this->whenLoaded('employeeType')),
            'employee_category_id' => $this->employee_category_id ? (int) $this->employee_category_id : null,
            'employment_type'      => new MetadataResource($this->whenLoaded('employmentType')),
            'work_shift_id'        => $this->workShift?->uuid,
            'work_shift'           => $this->relationLoaded('workShift') && $this->workShift ? [
                'uuid'                    => $this->workShift->uuid,
                'name'                    => $this->workShift->name,
                'start_time'              => $this->workShift->start_time
                    ? Carbon::parse($this->workShift->start_time)->format('g:i A')
                    : null,
                'end_time'                => $this->workShift->end_time
                    ? Carbon::parse($this->workShift->end_time)->format('g:i A')
                    : null,
                'weekend'                 => $this->workShift->weekend,
            ] : null,
            'work_location'        => $this->work_location,
            'hire_date'            => $this->hire_date ? $this->hire_date->format('Y-m-d') : null,
            'start_date'           => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'employment_duration'  => $this->employment_duration ? (int) $this->employment_duration : null,
            'hired_by'             => $this->hired_by ? (int) $this->hired_by : null,
            'reporting_manager'    => new HrmEmployeeResource($this->whenLoaded('reportingManager')),

            // ── Termination ───────────────────────────────────────────────────
            'termination_date'    => $this->termination_date ? $this->termination_date->format('Y-m-d') : null,
            'terminated_by'       => $this->terminated_by,
            'termination_reason'  => $this->termination_reason,
            'termination_remarks' => $this->termination_remarks,

            // ── Nested Relationships ──────────────────────────────────────────
            'education'           => $this->relationLoaded('education') ? $this->education->map(function ($edu) {
                return [
                    'uuid'         => $edu->uuid,
                    'degree_name'  => $edu->degree_name,
                    'institution'  => $edu->institution,
                    'passing_year' => $edu->passing_year,
                    'duration'     => $edu->duration,
                ];
            }) : null,

            'experience'          => $this->relationLoaded('experience') ? $this->experience->map(function ($exp) {
                return [
                    'uuid'         => $exp->uuid,
                    'company_name' => $exp->company_name,
                    'designation'  => $exp->designation,
                    'start_date'   => $exp->start_date ? $exp->start_date->format('Y-m-d') : null,
                    'end_date'     => $exp->end_date ? $exp->end_date->format('Y-m-d') : null,
                    'description'  => $exp->description,
                ];
            }) : null,

            'family'              => $this->relationLoaded('family') ? $this->family->map(function ($fam) {
                return [
                    'uuid'           => $fam->uuid,
                    'full_name'      => $fam->full_name,
                    'date_of_birth'  => $fam->date_of_birth ? $fam->date_of_birth->format('Y-m-d') : null,
                    'gender'         => $fam->gender ? new MetadataResource($fam->gender) : null,
                    'relationship'   => $fam->relationship ? new MetadataResource($fam->relationship) : null,
                    'marital_status' => $fam->maritalStatus ? new MetadataResource($fam->maritalStatus) : null,
                    'national_id'    => $fam->national_id,
                    'blood_group'    => $fam->bloodGroup ? new MetadataResource($fam->bloodGroup) : null,
                    'nationality'    => $fam->nationality,
                ];
            }) : null,

            'emergency_contacts'  => $this->relationLoaded('emergencyContacts') ? $this->emergencyContacts->map(function ($emg) {
                return [
                    'uuid'                     => $emg->uuid,
                    'contact_name'             => $emg->contact_name,
                    'relationship'             => $emg->relationship ? new MetadataResource($emg->relationship) : null,
                    'contact_number'           => $emg->contact_number,
                    'alternate_contact_number' => $emg->alternate_contact_number,
                    'address'                  => $emg->address,
                ];
            }) : null,

            'documents'           => $this->relationLoaded('documents') ? $this->documents->map(function ($doc) {
                return [
                    'uuid'  => $doc->uuid,
                    'title' => $doc->title,
                    'doc'   => $doc->doc ? asset(Storage::url($doc->doc)) : null,
                ];
            }) : null,

            'leave_balances'      => $this->relationLoaded('leaveBalances') ? $this->leaveBalances->map(function ($balance) {
                return [
                    'uuid'       => $balance->uuid,
                    'leave_type' => $balance->leaveType ? new MetadataResource($balance->leaveType) : null,
                    'year'       => $balance->year,
                    'total_days' => (float) $balance->total_days,
                    'used_days'  => (float) $balance->used_days,
                    'remaining'  => (float) max(0, $balance->total_days - $balance->used_days),
                ];
            }) : null,

            // ── Images / Files ────────────────────────────────────────────────
            'employee_photo'      => $this->employee_photo ? asset(Storage::url($this->employee_photo)) : null,
            'profile_picture'     => $this->profile_picture ? asset(Storage::url($this->profile_picture)) : null,
            'signature_image'     => $this->signature_image ? asset(Storage::url($this->signature_image)) : null,
            'nid_image'           => $this->nid_image ? asset(Storage::url($this->nid_image)) : null,
            'business_card_image' => $this->business_card_image ? asset(Storage::url($this->business_card_image)) : null,

            // ── System ────────────────────────────────────────────────────────
            'user_serial_no'      => $this->user_serial_no ? (int) $this->user_serial_no : null,
            'is_active'           => (bool) $this->is_active,
            'created_at'          => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'          => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
