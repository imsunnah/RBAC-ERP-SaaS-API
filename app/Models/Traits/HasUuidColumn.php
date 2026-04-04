<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasUuidColumn
{
    /**
     * Boot the trait to generate UUIDs on creation.
     */
    protected static function bootHasUuidColumn(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Use UUID for implicit route model binding instead of ID.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
