<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master karyawan untuk modul Kepatuhan Roster.
 *
 * Populasi yang DISINKRONKAN sengaja lebih luas daripada populasi yang wajib
 * dicek. Alasannya biaya: penarikan scan dari bcsid.mv_checkinout_rfid
 * dilakukan per RENTANG TANGGAL, bukan per daftar SID — jadi menarik 8 ribu
 * orang sama mahalnya dengan menarik 5 ribu. Yang membedakan hanya volume
 * tulis ke MySQL.
 *
 * Keuntungannya: bila definisi "wajib dicek" berubah (mis. syarat SIMPER
 * dilepas, atau daftar jabatan ditambah), cukup hitung ulang kolom
 * 'wajib_cek' — TIDAK perlu menarik ulang data RFID yang mahal itu.
 *
 * Kolom wp_grup / simper_aktif ikut disimpan supaya kartu ringkasan dan bar
 * chart per site bisa dibaca dari MySQL, tanpa bergantung pada koneksi OLAP
 * yang putus-sambung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_karyawan', function (Blueprint $table) {
            // increments (4 byte), bukan bigIncrements: populasi ±25 ribu dan
            // id ini diulang di setiap baris tabel fakta harian.
            $table->increments('id');

            $table->string('kode_sid', 50);
            $table->string('nik', 50)->nullable();
            $table->string('nama', 150);

            $table->string('jabatan_struktural', 150)->nullable();
            // Kategori rule engine: Operator A2B / Operator Hauler /
            // Operator Transportasi Massal / Mekanik / Lainnya.
            $table->string('kategori', 60)->nullable();

            $table->string('perusahaan', 255)->nullable();
            $table->string('kode_pt', 20)->nullable();
            $table->string('site', 60)->nullable();

            $table->string('status_karyawan', 50)->nullable();
            $table->string('status_permit', 50)->nullable();

            // Kelompok Working Permit unit: a2b / hauler / massal / tanpa.
            // Lihat config dms_roster.total_karyawan.wp_grup.
            $table->string('wp_grup', 20)->nullable();
            $table->boolean('simper_aktif')->default(false);

            // Inilah base yang wajib dicek (±5.454 orang). Sengaja kolom
            // tersendiri, bukan dihitung ulang tiap query.
            $table->boolean('wajib_cek')->default(false);

            $table->timestamp('disinkron_pada')->nullable();
            $table->timestamps();

            $table->unique('kode_sid', 'uq_drk_sid');
            // Dashboard hampir selalu menyaring wajib_cek dulu, lalu site/PT.
            $table->index(['wajib_cek', 'site'], 'idx_drk_wajib_site');
            $table->index(['wajib_cek', 'kode_pt'], 'idx_drk_wajib_pt');
            $table->index(['wajib_cek', 'wp_grup'], 'idx_drk_wajib_wp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_karyawan');
    }
};
