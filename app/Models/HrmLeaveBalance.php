<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveBalance extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization;

    protected $table = 'hrm_leave_balances';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'hrm_employee_id',
        'leave_type_id',
        'year',
        'total_days',
        'used_days',
        'pending_days',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year'       => 'integer',
        'total_days' => 'float',
        'used_days'  => 'float',
        'pending_days' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'leave_type_id');
    }
}
