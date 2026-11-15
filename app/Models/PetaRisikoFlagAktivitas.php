<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Flag status aktivitas per detail lokasi (Peta Risiko PJA DRM 2.1).
 * Lihat migrasi 2026_11_15_100000_create_peta_risiko_flag_aktivitas_table.
 */
class PetaRisikoFlagAktivitas extends Model
{
    public const AKTIF = 'AKTIF';

    public const NONAKTIF = 'NONAKTIF';

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    protected $table = 'peta_risiko_flag_aktivitas';

    protected $fillable = [
        'pja_id', 'pja_nama', 'detail_lokasi_id', 'detail_lokasi_nama', 'aktivitas_kunci', 'aktivitas_hash', 'aktivitas_nama',
        'status_aktivitas', 'mulai', 'selesai', 'alasan', 'status_review',
        'diajukan_oleh_id', 'diajukan_oleh_nama', 'direview_oleh_id', 'direview_oleh_nama', 'direview_pada', 'catatan_review',
    ];

    protected $casts = [
        'mulai' => 'date:Y-m-d',
        'selesai' => 'date:Y-m-d',
        'direview_pada' => 'datetime',
    ];

    public static function hash(string $aktivitasKunci): string
    {
        return sha1($aktivitasKunci);
    }
}
