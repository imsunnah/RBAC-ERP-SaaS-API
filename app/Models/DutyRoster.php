<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutyRoster extends Model
{
    use HasUuidColumn, BelongsToOrganization;
    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'is_off_day',
        'remarks',
        'organization_id',
        'company_id',
        'org_no',
        'created_by',
    ];

    protected $casts = [
        'date'       => 'date',
        'is_off_day' => 'boolean',
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

    // ── Scopes ────────────────────────────────────────────────

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
