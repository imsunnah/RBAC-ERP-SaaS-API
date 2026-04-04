<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an auditable action.
     *
     * @param string     $action         created|updated|deleted
     * @param Model      $model          The model being audited
     * @param array|null $oldValues      Values before change
     * @param array|null $newValues      Values after change
     * @param string|null $tags          Optional tag string
     */
    public function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $tags = null
    ): void {
        $orgId = null;

        try {
            $orgId = app('current_organization')?->id;
        } catch (\Throwable) {
            // No organization context — global action
        }

        AuditLog::create([
            'organization_id' => $orgId,
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'tags' => $tags,
            'created_at' => now(),
        ]);
    }

    /**
     * Convenience: log model creation.
     */
    public function logCreated(Model $model): void
    {
        $this->log('created', $model, null, $model->getAttributes());
    }

    /**
     * Convenience: log model update with diff.
     */
    public function logUpdated(Model $model, array $originalAttributes): void
    {
        $changed = array_keys($model->getDirty());

        $old = array_intersect_key($originalAttributes, array_flip($changed));
        $new = array_intersect_key($model->getAttributes(), array_flip($changed));

        $this->log('updated', $model, $old, $new);
    }

    /**
     * Convenience: log model deletion.
     */
    public function logDeleted(Model $model): void
    {
        $this->log('deleted', $model, $model->getAttributes());
    }

    /**
     * Log an arbitrary action (e.g. pivoting, syncing)
     */
    public function logAction(string $action, Model $model, string $tags, array $newValues): void
    {
        $this->log($action, $model, null, $newValues, $tags);
    }
}
