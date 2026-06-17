<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use HasFactory, HasUuidColumn, BelongsToOrganization;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'organization_id',
        'company_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
