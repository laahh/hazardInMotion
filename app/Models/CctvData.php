<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CctvData extends Model
{
    use HasFactory;

    protected $table = 'cctv_data_bmo2';

    protected $fillable = [
        'site',
        'perusahaan',
        'kategori',
        'no_cctv',
        'nama_cctv',
        'fungsi_cctv',
        'bentuk_instalasi_cctv',
        'jenis',
        'tipe_cctv',
        'radius_pengawasan',
        'jenis_spesifikasi_zoom',
        'lokasi_pemasangan',
        'control_room',
        'status',
        'kondisi',
        'longitude',
        'latitude',
        'coverage_lokasi',
        'coverage_detail_lokasi',
        'kategori_area_tercapture',
        'kategori_aktivitas_tercapture',
        'link_akses',
        'user_name',
        'password',
        'connected',
        'mirrored',
        'fitur_auto_alert',
        'keterangan',
        'verifikasi_by_petugas_ocr',
        'bulan_update',
        'tahun_update',
        'qr_code',
    ];

    protected $casts = [
        'longitude' => 'decimal:8',
        'latitude' => 'decimal:8',
        'bulan_update' => 'integer',
        'tahun_update' => 'integer',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }
}

