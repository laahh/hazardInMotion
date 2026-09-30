<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fakta harian hasil agregasi scan RFID PASSED (bcsid.mv_checkinout_rfid):
 * satu baris = satu karyawan pada satu tanggal. Dipisah dari tabel pola
 * supaya popup timeline bisa menampilkan gate & jam tanpa perlu memukul
 * Postgres OLAP (758 MB) di dalam siklus request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_rfid_days', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sid', 50);
            $table->date('tanggal');
            // Menit sejak 00:00. Check-in pertama menentukan Pagi/Malam.
            $table->unsignedSmallInteger('menit_checkin')->nullable();
            $table->unsignedSmallInteger('menit_checkout')->nullable();
            $table->string('gate_in', 255)->nullable();
            $table->string('gate_out', 255)->nullable();
            $table->timestamps();

            $table->unique(['kode_sid', 'tanggal'], 'uq_drrd_sid_tgl');
            $table->index('tanggal', 'idx_drrd_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_rfid_days');
    }
};
