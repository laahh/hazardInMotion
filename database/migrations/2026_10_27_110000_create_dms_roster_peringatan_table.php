<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peringatan dini roster — belum melanggar, tapi mendekati banned:
 *   onsite    periode kerja (on-site sejak cuti terakhir) hari ke-60 s/d 70
 *   beruntun  kerja tanpa off hari ke-10 s/d 13
 * Diisi tiap putaran dms:sync-roster-rfid (DmsRosterPeringatanStore), satu
 * baris per (tanggal, aturan, SID). Kolom notif_* diisi modul notifikasi.
 *
 * Nama constraint ditulis eksplisit (batas 64 karakter MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_peringatan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');                  // tanggal penilaian (hari ini saat dihitung)
            $table->string('kode', 20);               // onsite | beruntun
            $table->string('kode_sid', 20);
            $table->string('nama', 150)->nullable();
            $table->string('jabatan', 150)->nullable();
            $table->string('kategori', 50)->nullable();
            $table->string('perusahaan', 255)->nullable();
            $table->string('kode_pt', 50)->nullable();
            $table->string('site', 60)->nullable();
            $table->unsignedSmallInteger('hari_ke');  // hari ke-N periode kerja
            $table->smallInteger('sisa_hari');        // batas (70 / 13) - N
            $table->date('mulai_periode');
            $table->date('tgl_melanggar');            // perkiraan hari ke-71 (onsite) / ke-14 (beruntun)
            $table->date('tgl_banned');               // perkiraan hari ke-74 (onsite) / ke-14 (beruntun)
            $table->date('scan_terakhir')->nullable();
            $table->timestamp('notif_dikirim_pada')->nullable();
            $table->string('notif_keterangan', 255)->nullable();
            $table->timestamps();

            $table->unique(['tanggal', 'kode', 'kode_sid'], 'uq_drp_tgl_kode_sid');
            $table->index(['tanggal', 'notif_dikirim_pada'], 'idx_drp_tgl_notif');
            $table->index(['perusahaan', 'tanggal'], 'idx_drp_pt_tgl');
            $table->index('kode_sid', 'idx_drp_sid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_peringatan');
    }
};
