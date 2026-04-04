<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlan extends Model
{
    use HasUuidColumn, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'plan_name',
        'max_users',
        'max_modules',
        'billing_cycle',
        'status',
        'amount',
        'currency',
        'trial_ends_at',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active'])
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->status === 'trial' ? $this->trial_ends_at?->isFuture() : true);
    }

    public function isExpired(): bool
    {
        return !$this->isActive();
    }
}
