<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PJA Child/Mitra per PJA (keputusan user 26 Nov 2026, fitur kolom "PJA Child" di Alur Baseline): nama PJA anak
 * (id_parent di obds.m_pja) langsung di bawah PJA ini, dipisah baris baru — sama pola dengan kolom `perusahaan`.
 * Diisi backfill satu kali dari obds.m_pja (lihat DrmMasterDb::anakPerNamaPja), dan oleh
 * `php artisan peta-risiko:master-db --isi` berikutnya untuk pengisian dari awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drm_master_lokasi_pja', function (Blueprint $t) {
            $t->text('pja_anak')->nullable()->after('perusahaan')->comment('nama PJA anak (Mitra) langsung di bawah PJA ini, dipisah baris baru');
        });
    }

    public function down(): void
    {
        Schema::table('drm_master_lokasi_pja', function (Blueprint $t) {
            $t->dropColumn('pja_anak');
        });
    }
};
