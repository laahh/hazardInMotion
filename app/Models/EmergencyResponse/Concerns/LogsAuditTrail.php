<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Concerns;

use App\Models\EmergencyResponse\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Generic audit trail for Emergency Response module models.
 * Writes one AuditLog row per changed field on update (grouped by a
 * shared change_batch uuid), and one summary row on create/delete.
 *
 * Add `protected array $auditExcept = [...]` on the model to skip extra
 * columns beyond the default timestamp/password-like columns.
 */
trait LogsAuditTrail
{
    public static function bootLogsAuditTrail(): void
    {
        static::created(function ($model): void {
            $model->writeAuditLog('created', null, null, json_encode($model->getAttributes()));
        });

        static::updated(function ($model): void {
            $batch = (string) Str::uuid();

            foreach ($model->getChanges() as $field => $newValue) {
                if (in_array($field, $model->auditExcludedFields(), true)) {
                    continue;
                }

                $oldValue = $model->getOriginal($field);

                $model->writeAuditLog(
                    'updated',
                    $field,
                    is_scalar($oldValue) || $oldValue === null ? (string) $oldValue : json_encode($oldValue),
                    is_scalar($newValue) || $newValue === null ? (string) $newValue : json_encode($newValue),
                    $batch,
                );
            }
        });

        static::deleted(function ($model): void {
            $model->writeAuditLog('deleted', null, json_encode($model->getAttributes()), null);
        });
    }

    protected function auditExcludedFields(): array
    {
        return array_merge(['created_at', 'updated_at', 'deleted_at'], $this->auditExcept ?? []);
    }

    protected function writeAuditLog(
        string $action,
        ?string $field,
        ?string $oldValue,
        ?string $newValue,
        ?string $batch = null,
    ): void {
        AuditLog::create([
            'entity_type' => static::class,
            'entity_id' => $this->getKey(),
            'action' => $action,
            'field_name' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'change_batch' => $batch,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);
    }
}
