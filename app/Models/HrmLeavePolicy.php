<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeavePolicy extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization;

    protected $table = 'hrm_leave_policies';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'leave_type_id',
        'employee_type_id',
        'leave_balance',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'leave_balance' => 'float',
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'leave_type_id');
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'employee_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
