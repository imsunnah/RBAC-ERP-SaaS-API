<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasUuidColumn, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'organization_id',
        'parent_role_id',
        'name',
        'slug',
        'description',
        'is_inheritable',
        'level',
        'is_active',
    ];

    protected $casts = [
        'is_inheritable' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parentRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_role_id');
    }

    public function childRoles(): HasMany
    {
        return $this->hasMany(Role::class, 'parent_role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('is_granted')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['organization_id', 'company_id'])
            ->withTimestamps();
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'role_modules')->withTimestamps();
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menus')->withTimestamps();
    }

    public function submenus(): BelongsToMany
    {
        return $this->belongsToMany(Submenu::class, 'role_submenus')->withTimestamps();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve all permissions for this role including parent hierarchy.
     */
    public function resolvePermissions(): \Illuminate\Support\Collection
    {
        $perms = $this->permissions->keyBy('slug');

        if ($this->is_inheritable && $this->parentRole) {
            $inherited = $this->parentRole->resolvePermissions();
            // Parent permissions only fill gaps — child role wins on conflict
            $perms = $inherited->merge($perms);
        }

        return $perms;
    }
}
