<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan perubahan roster dari Form Edit per SID. Jadwalnya baru berubah
 * setelah disetujui dengan tanda tangan (DmsRosterPengajuanService).
 */
class DmsRosterPengajuan extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'dms_roster_pengajuan';

    protected $fillable = [
        'kode_sid',
        'nama',
        'perusahaan',
        'jabatan',
        'site',
        'perubahan',
        'status',
        'catatan',
        'diajukan_oleh',
        'direview_oleh',
        'direview_pada',
        'catatan_review',
        'ttd_path',
        'ttd_sumber',
    ];

    protected $casts = [
        'perubahan' => 'array',
        'direview_pada' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
