<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan unbanned pengawas dari form publik /form/pengajuan-treatment-banned.
 * Kolomnya mengikuti auto_banned_unban_requests; scr_daily_banned_id dan
 * scr_weekly_banned_id diganti tanggal_kerja + sid, kunci baris di
 * validasi_status_banned_pengawas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengawas_unban_requests', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_kerja');
            $table->string('sid', 64);
            $table->string('nik', 50)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('perusahaan', 255)->nullable();
            $table->string('site_dedicated', 255)->nullable();
            // TEXT, bukan varchar(255): sumbernya alasan_banned bertipe TEXT.
            $table->text('banned_reason')->nullable();
            $table->string('status_banned_ref', 255)->nullable();
            $table->text('alasan_pengajuan');
            $table->string('evidence_file_path', 512)->nullable();
            $table->string('evidence_original_name', 255)->nullable();
            $table->string('evidence_mime', 128)->nullable();
            $table->dateTime('evidence_uploaded_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->dateTime('hsct_notified_at')->nullable();
            $table->string('week', 8)->nullable();
            $table->string('iso_year', 8)->nullable();
            $table->unsignedBigInteger('submitted_by_id')->nullable();
            $table->string('submitted_by_name', 255)->nullable();
            $table->string('no_hp', 32)->nullable();
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->string('reviewed_by_name', 255)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('catatan_review')->nullable();
            $table->timestamps();

            $table->index(['sid', 'tanggal_kerja'], 'idx_pengawas_unban_requests_sid_tanggal');
            $table->index('status', 'idx_pengawas_unban_requests_status');
            $table->index(['site_dedicated', 'status'], 'idx_pengawas_unban_requests_site_status');
            $table->index('created_at', 'idx_pengawas_unban_requests_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengawas_unban_requests');
    }
};
