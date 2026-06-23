<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmLookupGroup extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hrmLookups(): HasMany
    {
        return $this->hasMany(HrmLookup::class);
    }
}
