<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmWeeklyMeal extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization, SoftDeletes;

    protected $table = 'hrm_weekly_meals';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'day',
        'lunch_item',
        'alternative_lunch',
        'expected_person',
        'chef_id',
        'chef_name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expected_person' => 'integer',
        'is_active'       => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function chef(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'chef_id');
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
