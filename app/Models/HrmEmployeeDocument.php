<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeDocument extends Model
{
    use HasFactory, HasUuidColumn;

    protected $table = 'hrm_employee_documents';

    protected $fillable = [
        'uuid',
        'hrm_employee_id',
        'title',
        'doc',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }
}
