<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\Shared\Concerns;

use App\Models\EmergencyResponse\Shared\EquipmentStatusHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Logs a friendly history row whenever `condition` or `operational_status`
 * changes on an equipment/safety-device model, separate from the generic
 * field-diff AuditLog — this one is shown to users on the detail page.
 */
trait TracksEquipmentStatusHistory
{
    public static function bootTracksEquipmentStatusHistory(): void
    {
        static::updated(function ($model): void {
            foreach (['condition', 'operational_status'] as $field) {
                if ($model->isDirty($field)) {
                    $model->statusHistories()->create([
                        'field_changed' => $field,
                        'old_value' => $model->getOriginal($field),
                        'new_value' => $model->{$field},
                        'changed_by' => Auth::id(),
                        'changed_at' => now(),
                    ]);
                }
            }
        });
    }

    public function statusHistories()
    {
        return $this->morphMany(EquipmentStatusHistory::class, 'trackable')->latest('changed_at');
    }
}
