<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmLeaveApplication extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization;

    protected $table = 'hrm_leave_applications';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'hrm_employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'start_session',
        'end_session',
        'total_days',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'cancelled_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'from_date'    => 'date',
        'to_date'      => 'date',
        'total_days'   => 'float',
        'approved_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'leave_type_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HrmLeaveApplicationDocument::class, 'hrm_leave_application_id');
    }
}
