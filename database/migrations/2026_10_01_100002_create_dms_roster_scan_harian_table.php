<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fakta harian scan RFID — satu baris per karyawan per tanggal.
 *
 * Ini tabel terbesar di modul ini: ±4.500 baris/hari, jadi sekitar 1,2 juta
 * baris untuk satu tahun. Karena itu setiap byte per baris dihitung.
 *
 * Keputusan desain dan alasannya:
 *
 * 1. TANPA kolom id surrogate. Kunci alaminya (karyawan_id, tanggal) sudah
 *    unik, jadi dipakai langsung sebagai PRIMARY KEY. Di InnoDB primary key
 *    adalah clustered index, sehingga membaca satu tahun milik SATU karyawan
 *    jadi pembacaan berurutan — persis pola akses panel detail dan kompilasi
 *    pola. Menambah id bigint justru memaksa satu indeks tambahan seukuran
 *    tabelnya sendiri.
 *
 * 2. karyawan_id (4 byte) menggantikan kode_sid varchar(50).
 *
 * 3. gate_masuk_id / gate_keluar_id (2 byte) menggantikan dua varchar(255).
 *
 * 4. TANPA created_at/updated_at. Baris ini fakta turunan yang di-upsert
 *    berulang oleh sinkronisasi; kapan barisnya ditulis tidak pernah dipakai.
 *    Jejak waktu sinkronisasi sudah ada di dms_roster_karyawan.disinkron_pada.
 *
 * Perbandingan lebar baris:
 *    skema lama  : id 8 + kode_sid ~50 + tanggal 3 + 2x2 + 2 gate ~60 + 2 timestamp 8  ≈ 130+ byte
 *    skema ini   : karyawan_id 4 + tanggal 3 + 2x2 + 2x2                               ≈ 15 byte
 * Pada 1,2 juta baris: ratusan MB turun jadi belasan MB (di luar indeks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_scan_harian', function (Blueprint $table) {
            $table->unsignedInteger('karyawan_id');
            $table->date('tanggal');

            // Menit sejak 00:00 (0–1439). Check-in PERTAMA hari itu yang
            // menentukan Pagi (<720) atau Malam (>=720).
            $table->unsignedSmallInteger('menit_masuk')->nullable();
            // Check-out TERAKHIR hari itu.
            $table->unsignedSmallInteger('menit_keluar')->nullable();

            $table->unsignedSmallInteger('gate_masuk_id')->nullable();
            $table->unsignedSmallInteger('gate_keluar_id')->nullable();

            $table->primary(['karyawan_id', 'tanggal']);

            // Sinkronisasi menarik & menimpa per rentang tanggal.
            $table->index('tanggal', 'idx_drsh_tanggal');

            $table->foreign('karyawan_id', 'fk_drsh_karyawan')
                ->references('id')->on('dms_roster_karyawan')
                ->cascadeOnDelete();

            $table->foreign('gate_masuk_id', 'fk_drsh_gate_in')
                ->references('id')->on('dms_roster_gate')
                ->nullOnDelete();

            $table->foreign('gate_keluar_id', 'fk_drsh_gate_out')
                ->references('id')->on('dms_roster_gate')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_scan_harian');
    }
};
