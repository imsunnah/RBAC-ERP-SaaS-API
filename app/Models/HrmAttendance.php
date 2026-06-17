<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmAttendance extends Model
{
    use HasFactory, HasUuidColumn, BelongsToOrganization;

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
        'device_user_id',
        'biometric_id',
        'employee_id',
        'date',
        'punch_in',
        'punch_out',
        'punch_state',
        'punch_type',
        'device_ip',
        'synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'      => 'date',
        'punch_in'  => 'datetime',
        'punch_out' => 'datetime',
        'synced_at' => 'datetime',
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
