<?php

declare(strict_types=1);

namespace App\Models\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal Rencana — plan-OCR.md T3.1. Satu baris = satu personil dijadwalkan
 * di satu site+tanggal+shift. Tidak ada foreign key ke sumber personil (beda
 * database) — lihat plan-OCR.md T3.0.
 */
final class SchedulePlan extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_LOCKED = 'locked';

    protected $table = 'control_room_schedule_plans';

    /**
     * Alasan perubahan — diisi controller sebelum update(). Wajib jika plan
     * sudah locked; untuk draft boleh kosong (observer mengisi default).
     */
    public ?string $changeReason = null;

    /**
     * Dipakai internal oleh SchedulePlanObserver untuk membawa nilai kolom
     * original dari event 'updating' ke 'updated' pada objek model yang sama.
     * Jangan diisi dari luar observer.
     *
     * @var array<string, mixed>|null
     */
    public ?array $pendingChangeLog = null;

    protected $fillable = [
        'site_code',
        'year',
        'week_number',
        'date',
        'shift_code',
        'personnel_source_key',
        'personnel_name_snapshot',
        'status',
        'locked_at',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'locked_at' => 'datetime',
        'site_code' => ControlRoomSiteCode::class,
        'shift_code' => ControlRoomShiftCode::class,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ScheduleChange::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
