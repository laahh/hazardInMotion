<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log eksekusi automation ban (RFID/SAP dst.) per record di
 * sid_roster_banned_master. Satu master_id bisa punya beberapa baris log
 * (retry) — automation_status: PROCESSING, SUCCESS, FAILED, SKIPPED.
 */
class SidRosterBannedLog extends Model
{
    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_SKIPPED = 'SKIPPED';

    protected $table = 'sid_roster_banned_log';

    protected $fillable = [
        'master_id',
        'nik',
        'sid',
        'nama',
        'perusahaan',
        'site_dedicated',
        'work_permit_kategori',
        'work_permit_jenis',
        'automation_status',
        'automation_step',
        'error_message',
        'screenshot_work_permit',
        'screenshot_karyawan_saved',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(SidRosterBannedMaster::class, 'master_id');
    }
}
