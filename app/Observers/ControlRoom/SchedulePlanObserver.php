<?php

declare(strict_types=1);

namespace App\Observers\ControlRoom;

use App\Models\ControlRoom\ScheduleChange;
use App\Models\ControlRoom\SchedulePlan;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * plan-OCR.md T3.2 — setiap perubahan pada SchedulePlan yang sudah `locked`
 * wajib menyertakan `changeReason` (diisi controller sebelum update()) dan
 * otomatis dicatat ke control_room_schedule_changes.
 */
final class SchedulePlanObserver
{
    /** @var list<string> */
    private const TRACKED_FIELDS = [
        'site_code',
        'date',
        'shift_code',
        'personnel_source_key',
        'personnel_name_snapshot',
    ];

    public function updating(SchedulePlan $plan): void
    {
        $wasLocked = $plan->getOriginal('status') === SchedulePlan::STATUS_LOCKED;
        $changedTrackedFields = array_intersect(array_keys($plan->getDirty()), self::TRACKED_FIELDS);

        if ($wasLocked && $changedTrackedFields !== [] && trim((string) $plan->changeReason) === '') {
            throw new InvalidArgumentException(
                'Alasan perubahan (changeReason) wajib diisi untuk mengubah jadwal yang sudah dikunci.'
            );
        }

        $plan->pendingChangeLog = ($wasLocked && $changedTrackedFields !== [])
            ? array_intersect_key($plan->getOriginal(), array_flip(self::TRACKED_FIELDS))
            : null;
    }

    public function updated(SchedulePlan $plan): void
    {
        $original = $plan->pendingChangeLog;
        $plan->pendingChangeLog = null;

        if ($original === null) {
            return;
        }

        foreach (self::TRACKED_FIELDS as $field) {
            if (! array_key_exists($field, $original)) {
                continue;
            }

            $oldValue = $this->toComparable($original[$field]);
            $newValue = $this->toComparable($plan->getAttribute($field));

            if ($oldValue === $newValue) {
                continue;
            }

            ScheduleChange::create([
                'schedule_plan_id' => $plan->id,
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => (string) $plan->changeReason,
                'changed_by' => Auth::id(),
                'changed_at' => now(),
            ]);
        }
    }

    private function toComparable(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return (string) $value;
    }
}
