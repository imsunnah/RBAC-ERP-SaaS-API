<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeExperience extends Model
{
    use HasFactory, HasUuidColumn;

    protected $table = 'hrm_employee_experience';

    protected $fillable = [
        'uuid',
        'hrm_employee_id',
        'company_name',
        'designation',
        'start_date',
        'end_date',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }
}
