<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengecualian baseline Overview (kelompok aktivitas × bahaya-risiko unik, identitas kel+hid sama dengan yang
 * dipakai rincian modal kartu KPI — lihat DrmBeranda::rincianBaris) — keputusan user 27 Nov 2026: baris yang
 * aktivitasnya sudah tidak berlaku lagi bisa "dinonaktifkan" dari hitungan Tidak Teridentifikasi (Open Risk) tanpa
 * menunggu peta-risiko:sync, dengan riwayat (siapa, kapan, alasan) tetap tersimpan — baris TIDAK dihapus saat
 * dikembalikan aktif, cuma kolom aktif di-set false, supaya histori tetap bisa dilihat.
 *
 * Kolom lv/kode/inc/tr: salinan field baris rincian ('u'/'g' — lihat DrmBeranda::rincianBaris) PADA SAAT di-flag,
 * dipakai index.blade.php untuk "menambal" (subtract) angka kartu KPI & treemap kelompok aktivitas (cakupan
 * Semua perusahaan) secara langsung di browser tanpa perlu memuat ulang seluruh rincian.json — lihat
 * terapkanPatchRingkas() di index.blade.php. Tab Per Group Lokasi TIDAK ikut ditambal (perlu breakdown per grup
 * lokasi yang tidak selalu tersedia saat baris di-flag dari tab lain) — tetap ikut peta-risiko:sync berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drm_baseline_pengecualian', function (Blueprint $t) {
            $t->id();
            $t->string('kel', 50)->comment('kode kelompok aktivitas, sama dengan drm_master_kelompok.kode');
            $t->string('hid', 50)->comment('id baris HIRA (ClickHouse) representatif kombinasi aktivitas/sub/bahaya unik ini');
            $t->string('aktivitas', 500)->default('');
            $t->string('sub', 500)->default('');
            $t->string('bahaya', 1000)->default('');
            $t->string('alasan', 500);
            $t->string('lv', 20)->default('');
            $t->string('kode', 20)->default('')->comment('status K/G/T per minggu, satu huruf per minggu, sama urutan dengan M.beranda.minggu');
            $t->boolean('inc')->default(false)->comment('pernah terkait insiden 12 bulan (laten) saat di-flag');
            $t->boolean('tr')->default(false)->comment('residual HIRA lebih rendah dari risiko awal (turun) saat di-flag');
            $t->boolean('aktif')->default(true);
            $t->string('dibuat_oleh', 190)->nullable();
            $t->string('dikembalikan_oleh', 190)->nullable();
            $t->timestamp('dikembalikan_pada')->nullable();
            $t->timestamps();
            $t->index(['kel', 'hid', 'aktif'], 'dbp_kel_hid_aktif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drm_baseline_pengecualian');
    }
};
