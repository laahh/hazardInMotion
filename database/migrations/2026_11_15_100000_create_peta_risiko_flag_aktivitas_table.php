<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag status aktivitas Peta Risiko PJA (DRM 2.1): PJA menandai aktivitas di satu detail lokasi
 * AKTIF / NONAKTIF untuk suatu periode. Alur: PENDING → APPROVED / REJECTED (reviewer ≠ pengaju).
 * Baris tidak diedit setelah direview; perubahan = pengajuan baru (riwayat tetap utuh).
 *
 * `aktivitas_kunci` = nama aktivitas HIRA huruf kecil (kunci di snapshot). Karena bisa panjang,
 * indeks memakai `aktivitas_hash` (sha1). Nama indeks ditulis eksplisit (batas 64 karakter MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peta_risiko_flag_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->string('pja_id', 32);
            $table->string('pja_nama', 255)->nullable();
            $table->string('detail_lokasi_id', 32);
            $table->string('detail_lokasi_nama', 255)->nullable();
            $table->text('aktivitas_kunci');
            $table->char('aktivitas_hash', 40);
            $table->string('aktivitas_nama', 500)->nullable();
            $table->string('status_aktivitas', 16);
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->text('alasan');
            $table->string('status_review', 16)->default('PENDING');
            $table->unsignedBigInteger('diajukan_oleh_id')->nullable();
            $table->string('diajukan_oleh_nama', 255)->nullable();
            $table->unsignedBigInteger('direview_oleh_id')->nullable();
            $table->string('direview_oleh_nama', 255)->nullable();
            $table->dateTime('direview_pada')->nullable();
            $table->text('catatan_review')->nullable();
            $table->timestamps();

            $table->index('pja_id', 'idx_prfa_pja');
            $table->index(['detail_lokasi_id', 'aktivitas_hash'], 'idx_prfa_dl_aktivitas');
            $table->index('status_review', 'idx_prfa_status_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peta_risiko_flag_aktivitas');
    }
};
