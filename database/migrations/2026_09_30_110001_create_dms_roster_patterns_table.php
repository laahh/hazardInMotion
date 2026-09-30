<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pola roster terkompilasi per karyawan per tahun — satu karakter = satu hari
 * (P = shift pagi, M = shift malam, o = tanpa check-in). Karakter ke-0 SELALU
 * 1 Januari tahun tersebut, jadi indeks hari bisa dihitung tanpa menyimpan
 * tanggal per karakter.
 *
 * Normalisasi o → c (Cuti) TIDAK disimpan di sini; itu dikerjakan rule engine
 * saat evaluasi supaya ambang 'off_ke_cuti' bisa diubah di config tanpa
 * perlu sinkronisasi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dms_roster_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sid', 50);
            $table->unsignedSmallInteger('tahun');
            $table->string('nama', 150);
            $table->string('jabatan', 255)->nullable();
            $table->string('kategori', 60);
            $table->string('perusahaan', 255);
            $table->string('kode_pt', 20);
            $table->string('site', 60)->nullable();
            // Panjang maksimum 366 (tahun kabisat).
            $table->string('pola', 366);
            $table->date('hari_terakhir');
            $table->timestamp('disinkron_pada')->nullable();
            $table->timestamps();

            $table->unique(['kode_sid', 'tahun'], 'uq_drp_sid_tahun');
            $table->index(['tahun', 'kode_pt'], 'idx_drp_tahun_pt');
            $table->index(['tahun', 'site'], 'idx_drp_tahun_site');
            $table->index('kategori', 'idx_drp_kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dms_roster_patterns');
    }
};
