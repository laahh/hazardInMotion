<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsidenTabel extends Model
{
    use HasFactory;

    protected $table = 'insiden_tabel';

    protected $fillable = [
        'no_kecelakaan',
        'kode_be_investigasi',
        'status_lpi',
        'target_penyelesaian_lpi',
        'actual_penyelesaian_lpi',
        'ketepatan_waktu_lpi',
        'tanggal',
        'bulan',
        'tahun',
        'minggu_ke',
        'hari',
        'jam',
        'menit',
        'shift',
        'perusahaan',
        'latitude',
        'longitude',
        'departemen',
        'site',
        'lokasi',
        'sublokasi',
        'lokasi_spesifik',
        'lokasi_validasi_hsecm',
        'pja',
        'insiden_dalam_site_mining',
        'kategori',
        'injury_status',
        'kronologis',
        'high_potential',
        'alat_terlibat',
        'nama',
        'jabatan',
        'shift_kerja_ke',
        'hari_kerja_ke',
        'npk',
        'umur',
        'range_umur',
        'masa_kerja_perusahaan_tahun',
        'masa_kerja_perusahaan_bulan',
        'range_masa_kerja_perusahaan',
        'masa_kerja_bc_tahun',
        'masa_kerja_bc_bulan',
        'range_masa_kerja_bc',
        'bagian_luka',
        'loss_cost',
        'saksi_langsung',
        'atasan_langsung',
        'jabatan_atasan_langsung',
        'kontak',
        'detail_kontak',
        'sumber_kecelakaan',
        'layer',
        'jenis_item_ipls',
        'detail_layer',
        'klasifikasi_layer',
        'keterangan_layer',
        'id_lokasi_insiden',
    ];

    protected $casts = [
        'target_penyelesaian_lpi' => 'date',
        'actual_penyelesaian_lpi' => 'date',
        'tanggal' => 'date',
        'loss_cost' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];
}

