<?php

declare(strict_types=1);

namespace App\Models\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Absen — plan-OCR.md T3.3. `schedule_plan_id` nullable = hadir tanpa
 * dijadwalkan. Tidak ada delete; koreksi lewat update() + correction_reason.
 */
final class Attendance extends Model
{
    public const STATUS_SESUAI_JADWAL = 'hadir_sesuai_jadwal';

    public const STATUS_MENGGANTIKAN = 'hadir_menggantikan';

    public const STATUS_TIDAK_HADIR = 'tidak_hadir';

    protected $table = 'control_room_attendances';

    protected $fillable = [
        'schedule_plan_id',
        'site_code',
        'date',
        'shift_code',
        'personnel_source_key',
        'personnel_name_snapshot',
        'status',
        'replacing_source_key',
        'absence_reason',
        'checked_in_at',
        'proof_path',
        'corrected_by',
        'correction_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'checked_in_at' => 'datetime',
        'site_code' => ControlRoomSiteCode::class,
        'shift_code' => ControlRoomShiftCode::class,
    ];

    public function schedulePlan(): BelongsTo
    {
        return $this->belongsTo(SchedulePlan::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function proofUrl(): ?string
    {
        $path = $this->getAttribute('proof_path');
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function proofIsImage(): bool
    {
        $path = (string) $this->getAttribute('proof_path');

        return preg_match('/\.(jpe?g|png|webp)$/i', $path) === 1;
    }
}
