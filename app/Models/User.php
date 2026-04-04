<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements OAuthenticatable
{
    use HasUuidColumn, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar',
        'phone',
        'is_super_admin',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'user_organizations')
            ->withPivot(['is_owner', 'is_primary', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function userOrganizations(): HasMany
    {
        return $this->hasMany(UserOrganization::class);
    }

    /**
     * Roles associated within a specific organization.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('organization_id')
            ->withTimestamps();
    }

    public function rolesInOrg(string $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->roles()->wherePivot('organization_id', $organizationId)->get();
    }

    /**
     * Direct permission overrides.
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot(['organization_id', 'is_granted'])
            ->withTimestamps();
    }

    public function directPermissionsInOrg(string $organizationId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->directPermissions()->wherePivot('organization_id', $organizationId)->get();
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(UserLoginAttempt::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->name;
    }

    public function belongsToOrganization(string $organizationId): bool
    {
        return $this->userOrganizations()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->exists();
    }
}
