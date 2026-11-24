<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perusahaan per PJA (keputusan user 24 Nov 2026): bukan nama PJA, tapi nama perusahaan personilnya (sama sumber
 * dengan filter "Perusahaan" di dashboard DRM — bep_vw_safety_all_karyawan.nama_perusahaan via m_pja_emp), diisi
 * `php artisan peta-risiko:master-db --isi` berikutnya (DrmSql::perusahaanPerPja, dicocokkan ke nama_pja per baris).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drm_master_lokasi_pja', function (Blueprint $t) {
            $t->text('perusahaan')->nullable()->after('nama_pja')->comment('nama perusahaan personil PJA ini, dipisah baris baru');
        });
    }

    public function down(): void
    {
        Schema::table('drm_master_lokasi_pja', function (Blueprint $t) {
            $t->dropColumn('perusahaan');
        });
    }
};
