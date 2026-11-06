<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoBannedUnbanStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan unbanned pengawas dari form publik /form/pengajuan-treatment-banned.
 * Data identitas disalin saat pengajuan dibuat, jadi tetap utuh walau baris
 * sumbernya di validasi_status_banned_pengawas berubah.
 */
class PengawasUnbanRequest extends Model
{
    protected $table = 'pengawas_unban_requests';

    protected $fillable = [
        'tanggal_kerja',
        'sid',
        'nik',
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
        'tanggal_kerja' => 'date',
        'status' => AutoBannedUnbanStatus::class,
        'evidence_uploaded_at' => 'datetime',
        'hsct_notified_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}
