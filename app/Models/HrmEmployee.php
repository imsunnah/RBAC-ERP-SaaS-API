<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use App\Models\HrmLookup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmEmployee extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'user_employee_id',
        'biometric_id',
        'national_id',
        'full_name',
        'employee_name_local',
        'date_of_birth',
        'gender_id',
        'blood_group_id',
        'religion_id',
        'marital_status_id',
        'country_id',
        'weight_kg',
        'height_cm',
        'eye_color',
        'office_email',
        'personal_email',
        'alternate_email',
        'office_mobile_no',
        'personal_mobile_no',
        'secondary_mobile_no',
        'tertiary_mobile_no',
        'emergency_contact',
        'present_address',
        'permanent_address',
        'emergency_address',
        'address_link',
        'department_id',
        'designation_id',
        'employee_type_id',
        'employee_category_id',
        'employment_type_id',
        'work_shift_id',
        'hire_date',
        'start_date',
        'employment_duration',
        'hired_by',
        'termination_date',
        'terminated_by',
        'termination_reason',
        'termination_remarks',
        'employee_photo',
        'profile_picture',
        'signature_image',
        'nid_image',
        'business_card_image',
        'passport_no',
        'passport_issue',
        'passport_expiry',
        'nationality',
        'reporting_manager_id',
        'work_location',
        'user_serial_no',
        'is_active',
        'org_no',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'start_date' => 'date',
        'termination_date' => 'date',
        'passport_issue' => 'date',
        'passport_expiry' => 'date',
        'weight_kg' => 'float',
        'height_cm' => 'float',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'department_id')
            ->whereHas('hrmLookupGroup', function ($q) {
                $q->where('slug', 'hrm-department');
            });
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'designation_id')
            ->whereHas('hrmLookupGroup', function ($q) {
                $q->where('slug', 'hrm-designation');
            });
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'gender_id');
    }

    public function bloodGroup(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'blood_group_id');
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'religion_id');
    }

    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'marital_status_id');
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'employee_type_id');
    }

    public function education()
    {
        return $this->hasMany(HrmEmployeeEducation::class, 'hrm_employee_id');
    }

    public function experience()
    {
        return $this->hasMany(HrmEmployeeExperience::class, 'hrm_employee_id');
    }

    public function family()
    {
        return $this->hasMany(HrmEmployeeFamily::class, 'hrm_employee_id');
    }

    public function emergencyContacts()
    {
        return $this->hasMany(HrmEmployeeEmergencyContact::class, 'hrm_employee_id');
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'reporting_manager_id');
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id', 'shift_id');
    }
}
