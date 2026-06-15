<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollConfig extends Model
{
    use HasUuidColumn;
    protected $fillable = [
        'key',
        'value',
        'type',
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

    public function scopeCurrentOrg(Builder $query): Builder
    {
        try {
            $org = app('current_organization');
            if ($org) {
                return $query->where('org_no', $org->id);
            }
        } catch (\Exception) {
            // Context not available
        }

        return $query;
    }
}
