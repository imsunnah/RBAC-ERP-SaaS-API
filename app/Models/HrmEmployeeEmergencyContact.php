<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeEmergencyContact extends Model
{
    use HasFactory, HasUuidColumn;

    protected $table = 'hrm_employee_emergency_contacts';

    protected $fillable = [
        'uuid',
        'hrm_employee_id',
        'contact_name',
        'relationship_id',
        'contact_number',
        'alternate_contact_number',
        'address',
    ];

    protected $casts = [
        'relationship_id' => 'integer',
    ];

    public function hrmEmployee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(GlobalLookup::class, 'relationship_id');
    }
}
