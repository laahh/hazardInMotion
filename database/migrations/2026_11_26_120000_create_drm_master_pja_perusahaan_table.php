<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perusahaan LANGSUNG (tidak digabung dengan induk/anak) per nama PJA apa pun — induk maupun anak (keputusan
 * user 26 Nov 2026, ditemukan lewat filter Site/Perusahaan di Alur Baseline: PJA Child yang tampil saat filter
 * Perusahaan aktif masih ikut subkontraktor lain yang kebetulan anak dari PJA induk yang sama, karena
 * drm_master_lokasi_pja.pja_anak cuma menyimpan NAMA anak, tidak menyimpan perusahaan anak itu sendiri).
 * Dipakai DrmKorelasi untuk menyaring PJA Child ke perusahaan terpilih. Diisi oleh
 * DrmMasterDb::perusahaanLangsungPerNamaPja() lewat `php artisan peta-risiko:master-db --isi` / backfill satu
 * kali untuk data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drm_master_pja_perusahaan', function (Blueprint $t) {
            $t->id();
            $t->string('nama_pja', 200);
            $t->text('perusahaan')->nullable()->comment('Perusahaan personil LANGSUNG PJA ini, dipisah baris baru — TIDAK digabung induk/anak');
            $t->timestamps();
            $t->unique('nama_pja', 'dmpp_nama_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drm_master_pja_perusahaan');
    }
};
