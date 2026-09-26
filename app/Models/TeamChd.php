<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamChd extends Model
{
    use HasUuidColumn, SoftDeletes;

    protected $table = 'team_chds';

    protected $fillable = [
        'team_mst_id',
        'department_id',
        'created_by',
        'updated_by',
        'active_status'
    ];

    protected function casts(): array
    {
        return [
            'active_status' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmLookup::class, 'department_id');
    }
}
