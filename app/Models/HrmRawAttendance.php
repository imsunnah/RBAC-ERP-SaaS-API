<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Raw attendance punch record from ZKTeco device.
 * One row per punch event — not aggregated.
 */
class HrmRawAttendance extends Model
{
    use HasUuidColumn;

    protected $table = 'hrm_raw_attendances';

    protected $fillable = [
        'uuid',
        'device_user_id',
        'punch_time',
        'punch_state',
        'punch_type',
        'device_ip',
        'raw_data',
        'processed_at',
    ];

    protected $casts = [
        'punch_time'   => 'datetime',
        'punch_state'  => 'integer',
        'punch_type'   => 'integer',
        'processed_at' => 'datetime',
    ];
}
