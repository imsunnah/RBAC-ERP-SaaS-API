<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmAttendance extends Model
{
    use HasFactory, HasUuidColumn, BelongsToOrganization, SoftDeletes;

    // ── Status Constants ──────────────────────────────────────
    const STATUS_PRESENT = 'PRESENT';
    const STATUS_LATE    = 'LATE';
    const STATUS_ABSENT  = 'ABSENT';

    // ── Source Constants ──────────────────────────────────────
    const SOURCE_BIOMETRIC = 'BIOMETRIC';
    const SOURCE_MANUAL    = 'MANUAL';
    const SOURCE_WEB       = 'WEB';

    // ── Approval Constants ───────────────────────────────────
    const APPROVAL_APPROVED = 'APPROVED';
    const APPROVAL_PENDING  = 'PENDING';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'biometric_id',
        'employee_id',
        'date',
        'status',
        'check_in',
        'check_out',
        'working_hours',
        'overtime_hours',
        'shift_id',
        'source',
        'approval_status',
        'remarks',
        'org_no',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'           => 'date',
        'check_in'       => 'datetime',
        'check_out'      => 'datetime',
        'working_hours'  => 'float',
        'overtime_hours' => 'float',
        'org_no'         => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id', 'shift_id');
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
