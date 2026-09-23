<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Automatically generates a UUID on model creation
 * and configures route-model binding to use UUID.
 *
 * ID remains the primary key for all internal DB operations.
 * UUID is used for external-facing lookups and route binding.
 *
 * @mixin Model
 */
trait HasUuidColumn
{
    /**
     * Boot the trait to generate UUIDs on creation.
     */
    public static function bootHasUuidColumn(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Initialize the trait — auto-add uuid to fillable.
     */
    public function initializeHasUuidColumn(): void
    {
        $this->mergeFillable(['uuid']);
    }

    /**
     * Use UUID for implicit route model binding instead of ID.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Find a model by its UUID or fail.
     */
    public static function findByUuid(string $uuid): static
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Find a model by its UUID or return null.
     */
    public static function findByUuidOrNull(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Resolve a UUID to the internal integer ID.
     */
    public static function resolveId(string $uuid): ?int
    {
        $model = new static();
        $keyName = $model->getKeyName();
        $value = static::where('uuid', $uuid)->value($keyName);

        return $value === null ? null : (int) $value;
    }
}
