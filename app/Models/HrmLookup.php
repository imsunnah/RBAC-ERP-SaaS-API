<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmLookup extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'hrm_lookup_group_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hrmLookupGroup(): BelongsTo
    {
        return $this->belongsTo(HrmLookupGroup::class);
    }
}
