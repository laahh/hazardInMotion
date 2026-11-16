<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel master mapping Peta Risiko PJA (DRM 2.1): setiap lapisan dari baseline HIRA sampai PJA bisa
 * dikoreksi lewat halaman /peta-risiko-pja/master. Diisi awal dari workbook master + aturan regex config
 * (php artisan peta-risiko:master-db --isi), lalu dibaca oleh peta-risiko:sync.
 * Nama index ditulis eksplisit (batas 64 karakter MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        // M01 baris HIRA v31 (baseline). ri = id_baris; aktif = ikut baseline.
        Schema::create('drm_master_baris', function (Blueprint $t) {
            $t->unsignedInteger('ri')->primary();
            $t->unsignedInteger('aid')->index('dmb_aid_idx');
            $t->string('aktivitas', 500);
            $t->string('sub', 500)->default('');
            $t->string('bahaya', 1000);
            $t->string('kb', 20)->default('');
            $t->boolean('umum')->default(false);
            $t->string('idh', 200)->default('')->comment('id_hira_master, dipisah koma');
            $t->boolean('aktif')->default(true);
            $t->string('catatan', 500)->nullable();
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
        });

        // M02 kelompok aktivitas + regex (dipakai untuk teks OBS/OAK dan aktivitas OAK checklist).
        Schema::create('drm_master_kelompok', function (Blueprint $t) {
            $t->string('kode', 10)->primary();
            $t->string('nama', 200);
            $t->string('regex', 2000)->default('');
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
        });

        // M03 aktivitas HIRA → kelompok aktivitas.
        Schema::create('drm_master_aktivitas', function (Blueprint $t) {
            $t->unsignedInteger('aid')->primary();
            $t->string('aktivitas', 500);
            $t->string('kelompok', 10)->index('dma_kel_idx');
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
        });

        // M04 grup lokasi.
        Schema::create('drm_master_grup', function (Blueprint $t) {
            $t->unsignedSmallInteger('gid')->primary();
            $t->string('nama', 120);
            $t->boolean('aktif')->default(true);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
        });

        // M05 grup lokasi → baris HIRA.
        Schema::create('drm_master_grup_baris', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('gid');
            $t->unsignedInteger('ri');
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->unique(['gid', 'ri'], 'dmgb_gid_ri_uq');
            $t->index('ri', 'dmgb_ri_idx');
        });

        // M06 detail lokasi (nama SAP) → grup lokasi.
        Schema::create('drm_master_dl', function (Blueprint $t) {
            $t->id();
            $t->string('site', 60);
            $t->string('lokasi', 200);
            $t->string('detail', 300);
            $t->unsignedSmallInteger('gid')->index('dmdl_gid_idx');
            $t->boolean('aktif')->default(true);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->index(['site', 'lokasi'], 'dmdl_site_lok_idx');
        });

        // M07/M08 lokasi → PJA (PJA melekat di level lokasi; detail lokasi mewarisi). nama_pja = nama di m_pja.
        Schema::create('drm_master_lokasi_pja', function (Blueprint $t) {
            $t->id();
            $t->string('site', 60);
            $t->string('lokasi', 200);
            $t->string('nama_pja', 200);
            $t->boolean('aktif')->default(true);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->index(['site', 'lokasi'], 'dmlp_site_lok_idx');
            $t->index('nama_pja', 'dmlp_pja_idx');
        });

        // M09 label Hazard/Inspeksi (ketidaksesuaian / subketidaksesuaian) → baris HIRA.
        Schema::create('drm_master_label', function (Blueprint $t) {
            $t->id();
            $t->string('ketidaksesuaian', 300);
            $t->string('subketidaksesuaian', 300);
            $t->unsignedInteger('ri');
            $t->string('sumber', 100)->default('');
            $t->boolean('aktif')->default(true);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->index('ri', 'dmlb_ri_idx');
            $t->index(['ketidaksesuaian', 'subketidaksesuaian'], 'dmlb_label_idx');
        });

        // M11/M12 teks aktivitas OBS / "aktivitas / sub" OAK → baris HIRA.
        Schema::create('drm_master_teks', function (Blueprint $t) {
            $t->id();
            $t->string('jenis', 3)->comment('OBS / OAK');
            $t->string('teks', 500);
            $t->unsignedInteger('ri');
            $t->boolean('aktif')->default(true);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->index('ri', 'dmtk_ri_idx');
            $t->index(['jenis', 'teks'], 'dmtk_jenis_teks_idx');
        });

        // M15 tema checklist + regex.
        Schema::create('drm_master_tema', function (Blueprint $t) {
            $t->unsignedSmallInteger('id')->primary();
            $t->string('nama', 120);
            $t->string('regex_teks', 2000);
            $t->string('regex_bahaya', 2000);
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
        });

        // M16 tema → baris HIRA.
        Schema::create('drm_master_tema_baris', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('tema_id');
            $t->unsignedInteger('ri');
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->timestamps();
            $t->unique(['tema_id', 'ri'], 'dmtb_tema_ri_uq');
            $t->index('ri', 'dmtb_ri_idx');
        });

        // Riwayat perubahan semua tabel master.
        Schema::create('drm_master_log', function (Blueprint $t) {
            $t->id();
            $t->string('tabel', 40);
            $t->string('kunci', 100);
            $t->string('aksi', 20)->comment('tambah / ubah / hapus / isi');
            $t->json('sebelum')->nullable();
            $t->json('sesudah')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('user_nama', 150)->nullable();
            $t->timestamp('dibuat')->useCurrent();
            $t->index(['tabel', 'kunci'], 'dmlog_tabel_kunci_idx');
            $t->index('dibuat', 'dmlog_dibuat_idx');
        });
    }

    public function down(): void
    {
        foreach (['drm_master_log', 'drm_master_tema_baris', 'drm_master_tema', 'drm_master_teks', 'drm_master_label',
            'drm_master_lokasi_pja', 'drm_master_dl', 'drm_master_grup_baris', 'drm_master_grup', 'drm_master_aktivitas',
            'drm_master_kelompok', 'drm_master_baris'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
