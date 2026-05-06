<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployee extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany;

    protected $fillable = [
        'uuid',
        'company_id',
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
        'user_serial_no',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'start_date' => 'date',
        'termination_date' => 'date',
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
}
