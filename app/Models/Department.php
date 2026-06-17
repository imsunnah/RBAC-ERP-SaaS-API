<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory, HasUuidColumn, BelongsToOrganization;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'organization_id',
        'company_id',
    ];


    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }


}
