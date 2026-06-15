<?php

namespace App\Http\Requests\Traits;

use Illuminate\Support\Arr;

/**
 * Trait to resolve UUIDs to IDs in FormRequests.
 * 
 * @method void merge(array $input)
 * @method \Illuminate\Validation\Validator getValidatorInstance()
 */
trait ResolvesUuidToId
{
    /**
     * Define which fields should be resolved from UUID to ID.
     * 
     * Format: 
     * [
     *   'field_name' => \App\Models\Model::class,
     *   'array_field' => [\App\Models\Model::class]
     * ]
     */
    protected function resolvedKeys(): array
    {
        return [];
    }

    /**
     * Overwrite validated data to include resolved IDs.
     */
    public function validated($key = null, $default = null)
    {
        // Use the default validated data from the validator
        $validated = $this->getValidatorInstance()->validated();

        if ($key !== null) {
            $value = data_get($validated, $key, $default);
            return $this->resolveValue($key, $value);
        }

        foreach ($this->resolvedKeys() as $field => $modelClass) {
            if (Arr::has($validated, $field)) {
                $validated[$field] = $this->resolveValue($field, Arr::get($validated, $field));
            }
        }

        return $validated;
    }

    /**
     * Resolve UUIDs to IDs and merge them back into the request.
     * This ensures $request->field_name returns the ID.
     */
    public function resolveUuids(): void
    {
        $resolved = [];
        $valid = $this->getValidatorInstance()->valid();

        foreach ($this->resolvedKeys() as $field => $modelClass) {
            if (Arr::has($valid, $field)) {
                $resolved[$field] = $this->resolveValue($field, Arr::get($valid, $field));
            }
        }

        if (!empty($resolved) && method_exists($this, 'merge')) {
            $this->merge($resolved);
        }
    }

    /**
     * Resolve a single value or array of values.
     */
    protected function resolveValue(string $field, mixed $value): mixed
    {
        $resolvedKeys = $this->resolvedKeys();

        if (!isset($resolvedKeys[$field]) || empty($value)) {
            return $value;
        }

        // If it's already an integer or array of integers, don't resolve again
        if (is_int($value) || (is_array($value) && !empty($value) && isset($value[0]) && is_int($value[0]))) {
            return $value;
        }

        $modelClass = $resolvedKeys[$field];

        if (is_array($modelClass)) {
            $class = $modelClass[0];
            $uuids = is_array($value) ? $value : [$value];

            // Only query if they look like UUIDs (not numeric)
            if (isset($uuids[0]) && !empty($uuids[0]) && !is_numeric($uuids[0])) {
                return $class::whereIn('uuid', $uuids)->pluck('id')->toArray();
            }
            return $value;
        }

        // Only resolve if it's not numeric (i.e., it's a UUID string)
        if (!empty($value) && !is_numeric($value)) {
            return $modelClass::resolveId($value);
        }

        return $value;
    }
}
