<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form /form/pengajuan-treatment-banned kini membaca dua sumber banned pengawas:
 * 'harian' (validasi_status_banned_pengawas) dan 'mingguan' (validasi_status_banned_pengawas_weekly).
 * Kolom `sumber` membedakan pengajuan keduanya; untuk mingguan, tanggal_kerja = Senin awal minggu.
 * Baris lama otomatis 'harian' (nilai bawaan). Sebelum kolom ini ada, form hanya menampilkan riwayat harian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengawas_unban_requests', function (Blueprint $table) {
            $table->string('sumber', 16)->default('harian')->after('tanggal_kerja');
            $table->index(['sid', 'sumber', 'tanggal_kerja'], 'idx_pengawas_unban_requests_sid_sumber_tgl');
        });
    }

    public function down(): void
    {
        Schema::table('pengawas_unban_requests', function (Blueprint $table) {
            $table->dropIndex('idx_pengawas_unban_requests_sid_sumber_tgl');
            $table->dropColumn('sumber');
        });
    }
};
