<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasUuidColumn, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'version',
        'is_core',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_modules')
            ->withPivot(['is_active', 'activated_at', 'expires_at'])
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_modules')->withTimestamps();
    }
}
