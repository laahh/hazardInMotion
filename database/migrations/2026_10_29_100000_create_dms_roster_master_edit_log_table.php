<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat edit Master Roster (menu Edit Roster): satu baris per perubahan
 * sel jadwal (kode satu tanggal) atau jam kerja satu karyawan. Hanya
 * menambah tabel baru — tabel roster lain tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dms_roster_master_edit_log')) {
            return;
        }

        Schema::create('dms_roster_master_edit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('upload_id');
            $table->string('kode_sid', 50);
            // NULL = perubahan jam kerja karyawan (bukan satu tanggal).
            $table->date('tanggal')->nullable();
            $table->string('kolom', 20);
            $table->string('nilai_lama', 60)->nullable();
            $table->string('nilai_baru', 60)->nullable();
            $table->string('diubah_oleh', 150);
            $table->timestamp('created_at')->nullable();

            $table->index(['upload_id', 'created_at'], 'idx_drmel_upload');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_master_edit_log');
    }
};
