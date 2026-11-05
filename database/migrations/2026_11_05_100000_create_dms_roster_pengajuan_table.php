<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan perubahan roster dari Form Edit per SID: satu baris per kiriman,
 * jadwalnya baru berubah setelah disetujui (menu Approval Pengajuan). Hanya
 * menambah tabel baru — tabel roster lain tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dms_roster_pengajuan')) {
            return;
        }

        Schema::create('dms_roster_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sid', 50);
            // Identitas saat diajukan, dari roster terbaru karyawan itu.
            $table->string('nama')->nullable();
            $table->string('perusahaan')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('site')->nullable();
            // [{tanggal, lama, baru}]
            $table->json('perubahan');
            $table->string('status', 20)->default('PENDING');
            $table->text('catatan')->nullable();
            $table->string('diajukan_oleh', 150);
            $table->string('direview_oleh', 150)->nullable();
            $table->timestamp('direview_pada')->nullable();
            $table->text('catatan_review')->nullable();
            $table->string('ttd_path')->nullable();
            $table->string('ttd_sumber', 20)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_drp_status');
            $table->index(['kode_sid', 'status'], 'idx_drp_sid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_pengajuan');
    }
};
