<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMst extends Model
{
    use HasUuidColumn, BelongsToOrganization, SoftDeletes;

    protected $table = 'team_msts';

    protected $fillable = [
        'team_name',
        'team_lead_name',
        'hrm_employee_id',
        'created_by',
        'updated_by',
        'active_status',
        'company_id',
        'organization_id',
    ];

    protected function casts(): array
    {
        return [
            'active_status' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }

    public function teamChd(): HasMany
    {
        return $this->hasMany(TeamChd::class, 'team_mst_id');
    }
}
