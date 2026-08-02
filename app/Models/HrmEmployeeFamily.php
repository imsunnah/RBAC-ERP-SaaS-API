<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeFamily extends Model
{
    use HasFactory, HasUuidColumn;

    protected $table = 'hrm_employee_family';

    protected $fillable = [
        'uuid',
        'hrm_employee_id',
        'full_name',
        'date_of_birth',
        'gender_id',
        'marital_status_id',
        'national_id',
        'blood_group_id',
        'nationality',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'gender_id');
    }

    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'marital_status_id');
    }

    public function bloodGroup(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'blood_group_id');
    }
}
