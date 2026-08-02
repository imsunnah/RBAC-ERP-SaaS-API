<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkShift extends Model
{
    use HasUuidColumn, BelongsToOrganization, SoftDeletes;
    protected $primaryKey = 'shift_id';

    protected $fillable = [
        'name',
        'short_code',
        'start_time',
        'end_time',
        'is_overnight',
        'break_duration_minutes',
        'expected_hours',
        'grace_minutes',
        'weekend',
        'is_default',
        'is_active',
        'organization_id',
        'company_id',
        'org_no',
    ];

    protected $casts = [
        'is_overnight'          => 'boolean',
        'break_duration_minutes' => 'integer',
        'expected_hours'        => 'float',
        'grace_minutes'         => 'integer',
        'is_default'            => 'boolean',
        'is_active'             => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    // ── Relationships ─────────────────────────────────────────

    public function rosters(): HasMany
    {
        return $this->hasMany(DutyRoster::class, 'shift_id', 'shift_id');
    }
}
