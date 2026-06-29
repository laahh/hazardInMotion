<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoBannedUnbanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoBannedUnbanRequest extends Model
{
    protected $table = 'auto_banned_unban_requests';

    protected $fillable = [
        'scr_daily_banned_id',
        'sid',
        'karyawan',
        'perusahaan',
        'site_dedicated',
        'banned_reason',
        'status_banned_ref',
        'alasan_pengajuan',
        'evidence_file_path',
        'evidence_original_name',
        'evidence_mime',
        'evidence_uploaded_at',
        'status',
        'hsct_notified_at',
        'week',
        'iso_year',
        'submitted_by_id',
        'submitted_by_name',
        'no_hp',
        'reviewed_by_id',
        'reviewed_by_name',
        'reviewed_at',
        'catatan_review',
    ];

    protected $casts = [
        'status' => AutoBannedUnbanStatus::class,
        'reviewed_at' => 'datetime',
        'evidence_uploaded_at' => 'datetime',
        'hsct_notified_at' => 'datetime',
    ];

    public function scrDailyBanned(): BelongsTo
    {
        return $this->belongsTo(ScrDailyBanned::class, 'scr_daily_banned_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * scr_daily_banned_id yang sudah pernah diajukan treatment untuk SID ini.
     *
     * @return array<int, int>
     */
    public static function requestedScrDailyBannedIdsForSid(string $sid): array
    {
        $sid = strtoupper(trim($sid));
        if ($sid === '') {
            return [];
        }

        return static::query()
            ->whereRaw('UPPER(TRIM(sid)) = ?', [$sid])
            ->whereNotNull('scr_daily_banned_id')
            ->pluck('scr_daily_banned_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
