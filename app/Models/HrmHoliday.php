<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrmHoliday extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization;

    protected $table = 'hrm_holidays';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'name',
        'date',
        'is_active',
    ];

    protected $casts = [
        'date'      => 'date',
        'is_active' => 'boolean',
    ];
}
