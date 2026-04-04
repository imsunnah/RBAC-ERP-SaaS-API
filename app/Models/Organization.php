<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasUuidColumn, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'domain',
        'timezone',
        'currency',
        'locale',
        'settings',
        'status',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_organizations')
            ->withPivot(['is_owner', 'is_primary', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function userOrganizations(): HasMany
    {
        return $this->hasMany(UserOrganization::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'organization_modules')
            ->withPivot(['is_active', 'activated_at', 'expires_at'])
            ->withTimestamps();
    }

    public function activeModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('is_active', true);
    }

    public function plan(): HasOne
    {
        return $this->hasOne(OrganizationPlan::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
