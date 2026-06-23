<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollConfig extends Model
{
    use HasUuidColumn, BelongsToOrganization, SoftDeletes;
    protected $fillable = [
        'key',
        'value',
        'type',
        'organization_id',
        'company_id',
        'org_no',
    ];

    // ── Accessor ──────────────────────────────────────────────

    /**
     * Return value cast to the declared type (string | int | float | time).
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'int'    => (int) $this->value,
            'float'  => (float) $this->value,
            default  => $this->value,
        };
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
