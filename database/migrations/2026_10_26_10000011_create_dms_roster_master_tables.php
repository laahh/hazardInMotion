<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Roster — jadwal kerja per karyawan yang di-upload admin (Excel) lalu
 * disetujui admin. Satu upload = satu file untuk satu periode; jadwalnya
 * disimpan sejak upload (status PENDING) supaya approver bisa melihat isinya
 * sebelum menyetujui.
 *
 * Jadwal yang berlaku untuk (SID, tanggal) = baris dari upload APPROVED yang
 * paling akhir disetujui (lihat DmsRosterMasterService).
 *
 * Nama constraint ditulis eksplisit: nama tabel panjang membuat nama bawaan
 * Laravel melewati batas 64 karakter MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_master_upload', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->string('departemen_site', 255)->nullable();
            $table->string('nama_file', 255);
            $table->string('path_file', 500);
            $table->unsignedInteger('jumlah_karyawan')->default(0);
            $table->unsignedInteger('jumlah_jadwal')->default(0);
            $table->string('status', 20)->default('PENDING');   // PENDING | APPROVED | REJECTED
            $table->text('catatan')->nullable();
            $table->string('diupload_oleh', 150);
            $table->string('direview_oleh', 150)->nullable();
            $table->timestamp('direview_pada')->nullable();
            $table->text('catatan_review')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_drmu_status');
        });

        Schema::create('dms_roster_master_jadwal', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('upload_id');
            $table->string('kode_sid', 20);
            $table->string('nik', 50)->nullable();
            $table->string('nama', 150)->nullable();
            $table->string('perusahaan', 255)->nullable();
            $table->string('jabatan', 150)->nullable();
            $table->string('site', 60)->nullable();
            $table->date('tanggal');
            $table->string('kode', 4);   // DS | NS | OF | OFF | CT | S | I
            // Jam efektif hari itu: kolom jam per karyawan bila diisi, kalau
            // tidak jam bawaan kodenya. Kosong untuk OFF/CT/S/I.
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->unsignedSmallInteger('menit_kerja')->nullable();

            $table->unique(['upload_id', 'kode_sid', 'tanggal'], 'uq_drmj_upload_sid_tgl');
            $table->index(['kode_sid', 'tanggal'], 'idx_drmj_sid_tgl');
            $table->foreign('upload_id', 'fk_drmj_upload')
                ->references('id')->on('dms_roster_master_upload')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_master_jadwal');
        Schema::dropIfExists('dms_roster_master_upload');
    }
};
