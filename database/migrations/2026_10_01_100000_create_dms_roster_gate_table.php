<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master gate scan RFID.
 *
 * Tabel lookup kecil (puluhan baris). Ada khusus supaya tabel fakta harian
 * cukup menyimpan id 2 byte, bukan mengulang nama gate sepanjang ±30 karakter
 * di setiap baris. Pada 1,2 juta baris fakta dengan dua kolom gate, itu
 * selisih puluhan MB yang tidak ada gunanya disimpan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_gate', function (Blueprint $table) {
            // smallIncrements: jumlah gate jauh di bawah 65.535.
            $table->smallIncrements('id');

            // Nama apa adanya dari bcsid.mv_checkinout_rfid.gate (sudah di-trim).
            $table->string('nama', 150);

            $table->timestamp('dibuat_pada')->nullable();

            $table->unique('nama', 'uq_drg_nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_gate');
    }
};
