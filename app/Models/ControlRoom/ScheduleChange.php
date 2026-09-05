<?php

declare(strict_types=1);

namespace App\Models\ControlRoom;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Change log jadwal — plan-OCR.md T3.2. Read-only untuk user; dibuat
 * otomatis oleh SchedulePlanObserver saat plan yang sudah locked diubah.
 */
final class ScheduleChange extends Model
{
    public $timestamps = false;

    protected $table = 'control_room_schedule_changes';

    protected $fillable = [
        'schedule_plan_id',
        'field',
        'old_value',
        'new_value',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function schedulePlan(): BelongsTo
    {
        return $this->belongsTo(SchedulePlan::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
