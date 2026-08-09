<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveApplicationDocument extends Model
{
    use HasFactory, HasUuidColumn;

    protected $table = 'hrm_leave_application_documents';

    protected $fillable = [
        'uuid',
        'hrm_leave_application_id',
        'title',
        'file_name',
        'file_path',
    ];

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(HrmLeaveApplication::class, 'hrm_leave_application_id');
    }
}
