<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu file Master Roster (jadwal kerja per karyawan) yang di-upload admin.
 * Jadwalnya di dms_roster_master_jadwal; berlaku setelah status APPROVED.
 */
class DmsRosterMasterUpload extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'dms_roster_master_upload';

    protected $fillable = [
        'judul',
        'periode_mulai',
        'periode_selesai',
        'departemen_site',
        'nama_file',
        'path_file',
        'jumlah_karyawan',
        'jumlah_jadwal',
        'status',
        'catatan',
        'diupload_oleh',
        'direview_oleh',
        'direview_pada',
        'catatan_review',
        'ttd_path',
        'ttd_sumber',
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'direview_pada' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Batch salah upload boleh dihapus (beserta seluruh jadwalnya) oleh
     * pengupload-nya atau admin, selama belum disetujui. diupload_oleh berisi
     * nama user saat upload, jadi dicocokkan lewat nama.
     */
    public function bolehDihapusOleh(?User $user): bool
    {
        if ($user === null || $this->status === self::STATUS_APPROVED) {
            return false;
        }

        return mb_strtolower(trim((string) $this->diupload_oleh)) === mb_strtolower(trim((string) $user->name))
            || $user->isAdmin();
    }
}
